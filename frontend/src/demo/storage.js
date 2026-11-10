// Compiled into the demo only. Preserve Web Storage's property access as well as methods.
export function scopedStorage(native, prefix) {
  const keys = () => Array.from({ length: native.length }, (_, i) => native.key(i)).filter(k => k?.startsWith(prefix));
  const api = {
    getItem: key => native.getItem(prefix + key),
    setItem: (key, value) => native.setItem(prefix + key, String(value)),
    removeItem: key => native.removeItem(prefix + key),
    clear: () => keys().forEach(key => native.removeItem(key)),
    key: index => keys()[index]?.slice(prefix.length) ?? null,
    get length() { return keys().length; },
  };
  return new Proxy(api, {
    get: (target, key) => typeof key === 'string' && !(key in target) ? native.getItem(prefix + key) ?? undefined : Reflect.get(target, key),
    set: (_, key, value) => { native.setItem(prefix + key, String(value)); return true; },
    deleteProperty: (_, key) => { native.removeItem(prefix + key); return true; },
  });
}
