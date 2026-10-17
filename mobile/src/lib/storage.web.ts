// Browser preview only (used for design review); the phone apps use the secure store.
export const storage = {
  get: async (key: string) => { try { return localStorage.getItem(key); } catch { return null; } },
  set: async (key: string, value: string) => { try { localStorage.setItem(key, value); } catch {} },
  remove: async (key: string) => { try { localStorage.removeItem(key); } catch {} },
};

export const KEYS = { server: 'bw.server', token: 'bw.token', pushToken: 'bw.push' };
