"""Instrument wire framing and conservative result extraction.

Parsing never supplies a clinical interpretation. Unknown fields are retained
in the original transmission for review.
"""
import re

VT, FS, CR = b"\x0b", b"\x1c", b"\r"
STX, ETX, ETB, EOT, ENQ, ACK, NAK, LF = (bytes([x]) for x in (2, 3, 23, 4, 5, 6, 21, 10))


def display(raw):
    """Readable, lossless-enough control character display for the operator."""
    return (raw.decode("utf-8", "replace").replace("\x0b", "<VT>")
            .replace("\x1c", "<FS>").replace("\x02", "<STX>")
            .replace("\x03", "<ETX>").replace("\x17", "<ETB>")
            .replace("\x04", "<EOT>").replace("\r", "\n"))


def field(parts, index):
    return parts[index] if len(parts) > index else ""


def component(value, sep="^"):
    return value.split(sep)[0].strip()


def hl7_escape(value, separators="|^~\\&"):
    esc = separators[3] if len(separators) > 3 else "\\"
    replacements = {"F": separators[0], "S": separators[1], "R": separators[2],
                    "E": esc, "T": separators[4] if len(separators) > 4 else "&"}
    return re.sub(re.escape(esc) + r"([FSRET])" + re.escape(esc),
                  lambda m: replacements[m.group(1)], value)


def parse_hl7(raw, mapping=None):
    text = raw.decode("utf-8", "replace").strip("\x0b\x1c\r\n")
    segments = [s for s in re.split(r"\r\n|\r|\n", text) if s]
    if not segments or not segments[0].startswith("MSH") or len(segments[0]) < 8:
        raise ValueError("HL7 message must start with a valid MSH segment")
    sep = segments[0][3]
    msh = segments[0].split(sep)
    encoding = field(msh, 1) or "^~\\&"
    comp = encoding[0]
    def get(parts, i):
        return hl7_escape(field(parts, i), sep + encoding)
    msg_type = get(msh, 8)
    if not (msg_type.startswith("ORU") or msg_type.startswith("ORF")):
        raise ValueError("Unsupported HL7 message type: " + msg_type)
    patient = accession = specimen = ""
    observations = []
    for line in segments[1:]:
        parts = line.split(sep)
        kind = parts[0]
        if kind == "PID":
            patient = component(get(parts, 3), comp)
        elif kind == "OBR":
            accession = component(get(parts, 3), comp) or component(get(parts, 2), comp)
        elif kind == "SPM":
            specimen = component(get(parts, 2), comp)
        elif kind == "OBX":
            codeparts = get(parts, 3).split(comp)
            code = codeparts[0].strip()
            if not code:
                continue
            item = (mapping or {}).get(code, {})
            observations.append({"code": code, "name": item.get("name") or (codeparts[1] if len(codeparts) > 1 else code),
                "value": get(parts, 5), "unit": item.get("unit") or component(get(parts, 6), comp),
                "reference": get(parts, 7), "flag": get(parts, 8), "status": get(parts, 11),
                "patient_id": patient, "accession": accession, "specimen": specimen})
    return {"format": "HL7", "message_type": msg_type, "control_id": get(msh, 9),
            "patient_id": patient, "accession": accession, "observations": observations,
            "version": get(msh, 11), "warnings": [] if observations else ["No OBX observations found"]}


def hl7_ack(raw, code="AA", explanation=""):
    try:
        first = raw.decode("utf-8", "replace").strip("\x0b").split("\r")[0]
        sep = first[3] if first.startswith("MSH") and len(first) > 3 else "|"
        msh = first.split(sep)
        control = field(msh, 9).replace("\r", "")
        source = field(msh, 2)
        facility = field(msh, 3)
        version = field(msh, 11) or "2.3.1"
    except Exception:
        sep, source, facility, control, version = "|", "", "", "", "2.3.1"
    from datetime import datetime, timezone
    now = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
    clean = lambda s: str(s).replace("\r", " ").replace(sep, " ")[:160]
    body = (f"MSH{sep}^~\\&{sep}LabBridge{sep}{sep}{clean(source)}{sep}{clean(facility)}{sep}{now}{sep}{sep}ACK{sep}ACK{now}{sep}P{sep}{version}\r"
            f"MSA{sep}{code}{sep}{clean(control)}{sep}{clean(explanation)}\r")
    return VT + body.encode("utf-8") + FS + CR


def parse_astm(raw, mapping=None, options=None):
    options = options or {}
    text = raw.decode("utf-8", "replace")
    records = [r for r in re.split(r"\r\n|\r|\n", text) if r]
    if not any(r.startswith("H|") for r in records):
        raise ValueError("ASTM content must contain an H record")
    if not any(r.startswith('L|') for r in records):
        raise ValueError('Incomplete ASTM transaction: L record missing')
    if records[0].startswith('H|\\!~|') and 'DxH 500!' in records[0]:
        raise ValueError('Select the dxh500 ASTM adapter for this instrument')
    patient = accession = ""
    observations = []
    for line in records:
        parts = line.split("|")
        if parts[0] == "P":
            patient = field(parts, options.get('astm_patient_field',3)).split("^")[0]
        elif parts[0] == "O":
            accession = field(parts, options.get('astm_sample_field',2)).split("^")[0]
        elif parts[0] == "R":
            identity = field(parts, 2).split("^")
            code = next((x for x in reversed(identity) if x.strip()), "").strip()
            component_index = options.get('astm_code_component',-1)
            if component_index >= 0: code = field(identity,component_index).strip()
            if not code:
                continue
            item = (mapping or {}).get(code, {})
            observations.append({"code": code, "name": item.get("name") or code,
                "value": field(parts, 3), "unit": item.get("unit") or field(parts, 4),
                "reference": field(parts, 5), "flag": field(parts, 6), "status": field(parts, 8),
                "patient_id": patient, "accession": accession, "specimen": ""})
    return {"format": "ASTM", "message_type": "RESULT", "control_id": "",
            "patient_id": patient, "accession": accession, "observations": observations,
            "version": "", "warnings": [] if observations else ["No R records found"]}


def parse_delimited(raw, mapping=None, delimiter=",", columns=None):
    from csv import reader
    from io import StringIO
    columns = columns or ["accession", "code", "value", "unit", "flag"]
    if len(delimiter) != 1:
        raise ValueError("Delimiter must be one character")
    observations = []
    for row in reader(StringIO(raw.decode("utf-8-sig", "replace")), delimiter=delimiter):
        if not any(row):
            continue
        values = dict(zip(columns, row))
        code = values.get("code", "").strip()
        if not code or code.lower() == "code":
            continue
        item = (mapping or {}).get(code, {})
        observations.append({k: values.get(k, "") for k in ("value", "reference", "flag", "status", "patient_id", "accession", "specimen")}
                            | {"code": code, "name": item.get("name") or code,
                               "unit": item.get("unit") or values.get("unit", "")})
    return {"format": "DELIMITED", "message_type": "RESULT", "control_id": "",
            "patient_id": observations[0]["patient_id"] if observations else "",
            "accession": observations[0]["accession"] if observations else "",
            "observations": observations, "version": "",
            "warnings": [] if observations else ["No result rows found"]}


def parse(raw, profile):
    options = profile.get('options',{})
    raw = raw.decode(options.get('encoding','utf-8'), errors='strict').encode('utf-8')
    fmt = profile["format"]
    mapping = profile.get("mapping", {})
    if fmt == "hl7": return parse_hl7(raw, mapping)
    if fmt == "astm":
        if options.get('astm_adapter') == 'dxh500':
            from dxh500 import parse_dxh500
            return parse_dxh500(raw, mapping, options)
        return parse_astm(raw, mapping,options)
    if fmt == "delimited": return parse_delimited(raw, mapping, profile.get("delimiter", ","), profile.get("columns"))
    raise ValueError("Unsupported format")


class MLLPStream:
    def __init__(self, limit=1024 * 1024):
        self.buffer = bytearray()
        self.limit = limit

    def feed(self, data):
        self.buffer.extend(data)
        messages = []
        while True:
            start = self.buffer.find(VT)
            if start < 0:
                if len(self.buffer) > self.limit: raise ValueError("MLLP start marker missing")
                break
            if start: del self.buffer[:start]
            end = self.buffer.find(FS + CR, 1)
            if end < 0:
                if len(self.buffer) > self.limit: raise ValueError("MLLP message too large")
                break
            messages.append(bytes(self.buffer[1:end]))
            del self.buffer[:end + 2]
        return messages


def astm_frame(number, payload, terminal=ETX):
    content = str(number % 8).encode() + payload + terminal
    checksum = sum(content) % 256
    return STX + content + f"{checksum:02X}".encode() + CR + LF


def validate_astm_frame(frame):
    if not frame.startswith(STX) or len(frame) < 7 or not frame.endswith(CR + LF):
        raise ValueError("Invalid ASTM frame")
    terminal_index = max(frame.rfind(ETX), frame.rfind(ETB))
    if terminal_index < 2 or len(frame[terminal_index + 1:-2]) != 2:
        raise ValueError("Missing ASTM terminator or checksum")
    actual = sum(frame[1:terminal_index + 1]) % 256
    try: expected = int(frame[terminal_index + 1:-2], 16)
    except ValueError: raise ValueError("Invalid ASTM checksum") from None
    if actual != expected: raise ValueError("ASTM checksum mismatch")
    return int(chr(frame[1])) if frame[1:2].isdigit() else -1, frame[2:terminal_index], frame[terminal_index:terminal_index + 1]
