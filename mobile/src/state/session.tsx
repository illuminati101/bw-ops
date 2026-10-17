import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import type { AppConfig, User } from '../api/types';
import { IS_CREW } from '../config';

type Session = {
  ready: boolean;
  server: string;
  token: string | null;
  user: User | null;
  config: AppConfig | null;
  configError: string | null;
  needsProfile: boolean;
  signIn: (token: string, user: User | null, needsProfile: boolean) => Promise<void>;
  signOut: () => Promise<void>;
  setServer: (url: string) => Promise<void>;
  refreshUser: () => Promise<User | null>;
  refreshConfig: () => Promise<void>;
  setUser: (u: User) => void;
};

const Ctx = createContext<Session | null>(null);

export function SessionProvider({ children }: { children: React.ReactNode }) {
  const [ready, setReady] = useState(false);
  const [server, setServerState] = useState(api.server);
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [config, setConfig] = useState<AppConfig | null>(null);
  const [configError, setConfigError] = useState<string | null>(null);
  const [needsProfile, setNeedsProfile] = useState(false);

  const refreshConfig = useCallback(async () => {
    try {
      setConfig(await api.get<AppConfig>('config'));
      setConfigError(null);
    } catch (e: any) {
      setConfigError(e?.message ?? 'Cannot reach the server.');
    }
  }, []);

  const refreshUser = useCallback(async () => {
    if (!api.token || IS_CREW) return null;
    try {
      const r = await api.get<{ user: User }>('me');
      setUser(r.user);
      setNeedsProfile(!r.user.email || r.user.name === 'New client' || !r.user.terms_accepted);
      return r.user;
    } catch {
      return null;
    }
  }, []);

  const signOut = useCallback(async () => {
    try {
      if (api.token) await api.post('auth/logout');
    } catch {}
    await api.setToken(null);
    setToken(null);
    setUser(null);
    setNeedsProfile(false);
  }, []);

  useEffect(() => {
    api.onUnauthorized(() => {
      api.setToken(null);
      setToken(null);
      setUser(null);
    });
    (async () => {
      const s = await api.load();
      setServerState(s.server);
      setToken(s.token);
      await refreshConfig();
      if (s.token) await refreshUser();
      setReady(true);
    })();
  }, [refreshConfig, refreshUser]);

  const value = useMemo<Session>(() => ({
    ready, server, token, user, config, configError, needsProfile,
    signIn: async (t, u, np) => {
      await api.setToken(t);
      setToken(t);
      setUser(u);
      setNeedsProfile(np);
    },
    signOut,
    setServer: async (url) => {
      await api.setServer(url);
      setServerState(api.server);
      await api.setToken(null);
      setToken(null);
      setUser(null);
      setConfig(null);
      await refreshConfig();
    },
    refreshUser,
    refreshConfig,
    setUser: (u) => {
      setUser(u);
      setNeedsProfile(!u.email || u.name === 'New client' || !u.terms_accepted);
    },
  }), [ready, server, token, user, config, configError, needsProfile, signOut, refreshUser, refreshConfig]);

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useSession() {
  const s = useContext(Ctx);
  if (!s) throw new Error('useSession outside provider');
  return s;
}

export function useFeature(key: string) {
  const { config } = useSession();
  return config?.features?.[key] ?? false;
}
