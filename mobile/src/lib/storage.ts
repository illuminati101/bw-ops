import * as SecureStore from 'expo-secure-store';

// AFTER_FIRST_UNLOCK so the Crew app's background location task can read the token while the phone is locked.
const opts: SecureStore.SecureStoreOptions = { keychainAccessible: SecureStore.AFTER_FIRST_UNLOCK };

export const storage = {
  get: (key: string) => SecureStore.getItemAsync(key, opts).catch(() => null),
  set: (key: string, value: string) => SecureStore.setItemAsync(key, value, opts),
  remove: (key: string) => SecureStore.deleteItemAsync(key, opts).catch(() => undefined),
};

export const KEYS = { server: 'bw.server', token: 'bw.token', pushToken: 'bw.push' };
