// Compiled into the demo only. The live application's storage is never cleared/read.
export function scopedStorage(native, prefix) {
  const keys = () => Array.from({ length: native.length }, (_, i) => native.key(i)).filter(k => k?.startsWith(prefix));
  return {
    getItem: key => native.getItem(prefix + key),
    setItem: (key, value) => native.setItem(prefix + key, String(value)),
    removeItem: key => native.removeItem(prefix + key),
    clear: () => keys().forEach(key => native.removeItem(key)),
    key: index => keys()[index]?.slice(prefix.length) ?? null,
    get length() { return keys().length; },
  };
}
