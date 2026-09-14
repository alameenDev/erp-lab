// Variable helpers for custom-template tests (تحاليل مخصصة).
//
// Why this exists:
//  - Sub-test names can contain spaces/non-ASCII (e.g. "Pus Cells", "كريات").
//  - The print replace pipeline matches on exact {{...}} strings, so a space
//    in a name is fragile and easy to typo.
//  - We expose a canonical `key` derived from the name, and keep the original
//    name as the row label. The template always stores the canonical key.

export const VAR_FIELDS = [
     { field: "value", labelEn: "result value", labelAr: "قيمة النتيجة" },
     { field: "name", labelEn: "display name", labelAr: "اسم العرض" },
     { field: "comment", labelEn: "comment", labelAr: "ملاحظة" },
];

export const slugifyKey = (raw) => {
     if (!raw) return "";
     return String(raw)
          .trim()
          .replace(/\s+/g, "_")
          .replace(/[^\w؀-ۿ]/g, "")
          .slice(0, 64);
};

export const buildVar = (name, field = "value") => {
     const key = slugifyKey(name);
     return `{{sub_test.${key}.${field}}}`;
};

// List every {sub_test.X.field} candidate for a given sub_tests array.
export const listVariables = (subTests = []) => {
     const list = [];
     (subTests || []).forEach((st) => {
          if (!st?.name) return;
          const key = slugifyKey(st.name);
          VAR_FIELDS.forEach(({ field, labelEn, labelAr }) => {
               list.push({
                    key,
                    name: st.name,
                    field,
                    labelEn,
                    labelAr,
                    insertText: `{{sub_test.${key}.${field}}}`,
               });
          });
     });
     return list;
};

// Find all {{...}} placeholders in an HTML string and classify them as
// resolvable (match an existing sub-test) or broken (no match).
export const lintTemplate = (html, subTests = []) => {
     const known = new Set();
     (subTests || []).forEach((st) => {
          if (!st?.name) return;
          const key = slugifyKey(st.name);
          VAR_FIELDS.forEach(({ field }) => known.add(`sub_test.${key}.${field}`));
          // Also accept the raw-name legacy form for backward compat
          VAR_FIELDS.forEach(({ field }) => known.add(`sub_test.${st.name}.${field}`));
     });
     const matches = [...(html || "").matchAll(/{{\s*([^{}]+?)\s*}}/g)];
     const broken = [];
     const resolved = [];
     matches.forEach((m) => {
          const token = m[1].trim();
          if (known.has(token)) resolved.push(token);
          else broken.push(token);
     });
     return { resolved, broken, total: matches.length };
};
