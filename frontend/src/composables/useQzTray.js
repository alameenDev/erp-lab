import { ref } from "vue";
import qz from "qz-tray";

// Shared (module-level) state so every component using this composable
// sees the same connection status instead of connecting separately.
const connected = ref(false);
const connecting = ref(false);
const printers = ref([]);
const lastError = ref("");

/**
 * Thin wrapper around QZ Tray (https://qz.io) that lets the app print
 * directly to a named, installed printer without opening the browser's
 * print dialog - used so each document type (invoice/thermal/barcode/
 * result) can go to its own configured printer automatically.
 *
 * Running unsigned: QZ Tray will show a one-time "allow this site to
 * print?" prompt on the local machine the first time a print/connect
 * call is made from a new browser profile. No certificate/signature
 * setup is configured here on purpose (see settings.printer_config
 * docs) - if a signed certificate is added later, call
 * qz.security.setCertificatePromise/setSignaturePromise once, before
 * connect(), to remove that prompt.
 */
export function useQzTray() {
  const isAvailable = () => typeof qz !== "undefined";

  const connect = async () => {
    if (connected.value) return true;
    if (connecting.value) return false;
    connecting.value = true;
    lastError.value = "";
    try {
      if (!qz.websocket.isActive()) {
        await qz.websocket.connect({ retries: 1, delay: 1 });
      }
      connected.value = true;
      return true;
    } catch (e) {
      connected.value = false;
      lastError.value = e?.message || "تعذر الاتصال ببرنامج QZ Tray - تأكد أنه مثبت وشغّال على هذا الجهاز";
      return false;
    } finally {
      connecting.value = false;
    }
  };

  const disconnect = async () => {
    try {
      if (qz.websocket.isActive()) await qz.websocket.disconnect();
    } catch (e) {
      // ignore
    } finally {
      connected.value = false;
    }
  };

  const listPrinters = async () => {
    const ok = await connect();
    if (!ok) return [];
    try {
      const found = await qz.printers.find();
      printers.value = Array.isArray(found) ? found : [found];
      return printers.value;
    } catch (e) {
      lastError.value = e?.message || "تعذر جلب قائمة الطابعات";
      return [];
    }
  };

  /**
   * Prints an HTML string (usually an element's outerHTML plus its
   * print CSS wrapped in <html><head><style>...</style></head><body>)
   * directly to the given printer, with no dialog.
   */
  const printHtml = async (printerName, html) => {
    if (!printerName) throw new Error("لم يتم تحديد اسم طابعة لهذا النوع من الطباعة");
    const ok = await connect();
    if (!ok) throw new Error(lastError.value || "QZ Tray غير متصل");

    const config = qz.configs.create(printerName);
    await qz.print(config, [
      { type: "pixel", format: "html", flavor: "plain", data: html },
    ]);
  };

  return {
    connected,
    connecting,
    printers,
    lastError,
    isAvailable,
    connect,
    disconnect,
    listPrinters,
    printHtml,
  };
}
