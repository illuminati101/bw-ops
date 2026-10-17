import { Platform } from 'react-native';
import { APP_VERSION, DEFAULT_SERVER, VARIANT } from '../config';
import { KEYS, storage } from '../lib/storage';

export class ApiError extends Error {
  status: number;
  errors: Record<string, string[]>;
  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
  field(name: string): string | undefined {
    return this.errors[name]?.[0];
  }
}

let server = DEFAULT_SERVER;
let token: string | null = null;
let onUnauthorized: (() => void) | null = null;

export const api = {
  async load() {
    server = (await storage.get(KEYS.server)) || DEFAULT_SERVER;
    token = await storage.get(KEYS.token);
    return { server, token };
  },
  get server() {
    return server;
  },
  get token() {
    return token;
  },
  async setServer(url: string) {
    server = normaliseServer(url);
    await storage.set(KEYS.server, server);
  },
  async setToken(t: string | null) {
    token = t;
    if (t) await storage.set(KEYS.token, t);
    else await storage.remove(KEYS.token);
  },
  onUnauthorized(fn: () => void) {
    onUnauthorized = fn;
  },
  url(path: string) {
    return `${server}/api/v1/${path.replace(/^\//, '')}`;
  },
  get: <T = any>(path: string, query?: Record<string, any>) => request<T>('GET', path, undefined, query),
  post: <T = any>(path: string, body?: any) => request<T>('POST', path, body),
  patch: <T = any>(path: string, body?: any) => request<T>('PATCH', path, body),
  delete: <T = any>(path: string) => request<T>('DELETE', path),
  upload: <T = any>(path: string, form: FormData) => request<T>('POST', path, form),
};

export function normaliseServer(url: string) {
  let u = url.trim().replace(/\/+$/, '').replace(/\/api\/v1$/, '');
  if (!/^https?:\/\//i.test(u)) u = `https://${u}`;
  return u;
}

async function request<T>(method: string, path: string, body?: any, query?: Record<string, any>): Promise<T> {
  let url = api.url(path);
  if (query) {
    const qs = Object.entries(query)
      .filter(([, v]) => v !== undefined && v !== null && v !== '')
      .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`)
      .join('&');
    if (qs) url += `?${qs}`;
  }
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-App': VARIANT,
    'X-App-Version': APP_VERSION,
    'X-Platform': Platform.OS,
  };
  const isForm = typeof FormData !== 'undefined' && body instanceof FormData;
  if (body !== undefined && !isForm) headers['Content-Type'] = 'application/json';
  if (token) headers.Authorization = `Bearer ${token}`;

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), isForm ? 90000 : 20000);
  let res: Response;
  try {
    res = await fetch(url, { method, headers, body: body === undefined ? undefined : isForm ? body : JSON.stringify(body), signal: controller.signal });
  } catch (e: any) {
    throw new ApiError(e?.name === 'AbortError' ? 'The server took too long to respond. Check your connection and try again.' : 'Cannot reach the server. Check your connection, or the server address in Settings.', 0);
  } finally {
    clearTimeout(timer);
  }
  if (res.status === 204) return undefined as T;
  let data: any = null;
  const text = await res.text();
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = null;
  }
  if (!res.ok) {
    if (res.status === 401 && token) onUnauthorized?.();
    const message = data?.errors ? (Object.values(data.errors)[0] as string[])[0] : data?.message;
    throw new ApiError(
      message || (res.status === 429 ? 'Too many attempts. Please wait a minute and try again.' : res.status >= 500 ? 'Something went wrong on our side. Please try again, or call the control room.' : `Request failed (${res.status}).`),
      res.status,
      data?.errors ?? {},
    );
  }
  return data as T;
}

export function errorMessage(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Something went wrong. Please try again.';
}
