import { useCallback, useEffect, useRef, useState } from 'react';
import { AppState } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { errorMessage } from '../api/client';

/** Load data when the screen is focused, and optionally refresh on an interval while it stays focused and the app is open. */
export function useLoader<T>(fn: () => Promise<T>, deps: any[] = [], intervalMs?: number) {
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const fnRef = useRef(fn);
  fnRef.current = fn;

  const load = useCallback(async (mode: 'initial' | 'refresh' | 'silent' = 'silent') => {
    if (mode === 'refresh') setRefreshing(true);
    try {
      const d = await fnRef.current();
      setData(d);
      setError(null);
    } catch (e) {
      if (mode !== 'silent') setError(errorMessage(e));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);

  useFocusEffect(
    useCallback(() => {
      load(data === null ? 'initial' : 'silent');
      if (!intervalMs) return undefined;
      const t = setInterval(() => {
        if (AppState.currentState === 'active') load('silent');
      }, intervalMs);
      return () => clearInterval(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [load, intervalMs]),
  );

  return { data, setData, error, loading, refreshing, reload: () => load('refresh'), silentReload: () => load('silent') };
}

export function useInterval(fn: () => void, ms: number | null) {
  const ref = useRef(fn);
  ref.current = fn;
  useEffect(() => {
    if (ms === null) return undefined;
    const t = setInterval(() => ref.current(), ms);
    return () => clearInterval(t);
  }, [ms]);
}
