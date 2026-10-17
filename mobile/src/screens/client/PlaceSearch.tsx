import React, { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, TextInput, View } from 'react-native';
import { api, errorMessage } from '../../api/client';
import type { Place } from '../../api/types';
import { currentPosition } from '../../lib/location';
import { useDraft } from '../../state/draft';
import { colors, fonts, radius, space } from '../../theme';
import { Body, Icon, Muted, Screen, Small } from '../../components/ui';

/** Address search for pickup, drop-off and stops. Uses the server's search (Google Places when configured, OpenStreetMap otherwise). */
export default function PlaceSearch({ navigation, route }: any) {
  const field: 'pickup' | 'dropoff' | 'stop' = route.params?.field ?? 'pickup';
  const index: number | undefined = route.params?.index;
  const then: string | undefined = route.params?.then;
  const { draft, set } = useDraft();
  const [q, setQ] = useState('');
  const [results, setResults] = useState<Place[]>([]);
  const [busy, setBusy] = useState(false);
  const [locating, setLocating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const session = useRef(Math.random().toString(36).slice(2));

  useEffect(() => {
    navigation.setOptions({ title: field === 'pickup' ? (draft.service && !draft.service.includes_transport ? 'Meeting point' : 'Pickup') : field === 'dropoff' ? 'Drop-off' : `Stop ${(index ?? 0) + 1}` });
  }, [field, index, navigation, draft.service]);

  useEffect(() => {
    if (q.trim().length < 3) {
      setResults([]);
      return undefined;
    }
    const t = setTimeout(async () => {
      setBusy(true);
      try {
        const r = await api.get<{ results: Place[] }>('places/search', { q, session: session.current });
        setResults(r.results);
        setError(null);
      } catch (e) {
        setError(errorMessage(e));
      } finally {
        setBusy(false);
      }
    }, 350);
    return () => clearTimeout(t);
  }, [q]);

  function apply(place: Place) {
    const value = { address: place.address, lat: Number(place.lat), lng: Number(place.lng) };
    if (field === 'pickup') set({ pickup: value });
    else if (field === 'dropoff') set({ dropoff: value });
    else {
      const stops = [...draft.stops];
      if (index !== undefined && index < stops.length) stops[index] = { ...stops[index], ...value };
      else stops.push({ ...value, wait_minutes: 15 });
      set({ stops });
    }
    if (then) navigation.replace(then);
    else navigation.goBack();
  }

  async function choose(p: Place) {
    if (p.lat == null && p.place_id) {
      setBusy(true);
      try {
        const r = await api.get<{ place: Place | null }>('places/details', { place_id: p.place_id, session: session.current });
        if (r.place) apply(r.place);
        else setError('Could not find that place. Try another result.');
      } catch (e) {
        setError(errorMessage(e));
      } finally {
        setBusy(false);
      }
      return;
    }
    apply(p);
  }

  async function useMyLocation() {
    setLocating(true);
    setError(null);
    const pos = await currentPosition();
    if (!pos) {
      setLocating(false);
      setError('Location is off or not allowed. Search for an address instead.');
      return;
    }
    let address = 'Current location';
    try {
      address = (await api.get<{ address: string | null }>('places/reverse', pos)).address ?? address;
    } catch {}
    setLocating(false);
    apply({ address, ...pos });
  }

  return (
    <Screen scroll={false} edges={['bottom']} contentStyle={{ paddingTop: space.md }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.md, minHeight: 54, borderRadius: radius.md, backgroundColor: colors.graphite, paddingHorizontal: space.lg }}>
        <Icon name="search" color={colors.gold} />
        <TextInput
          value={q}
          onChangeText={setQ}
          placeholder="Address, building or place"
          placeholderTextColor={colors.ash}
          selectionColor={colors.gold}
          autoFocus
          autoCorrect={false}
          style={{ flex: 1, color: colors.bone, fontFamily: fonts.body, fontSize: 16, paddingVertical: 12 }}
          returnKeyType="search"
        />
        {busy ? <ActivityIndicator color={colors.gold} /> : q ? <Pressable onPress={() => setQ('')} accessibilityLabel="Clear"><Icon name="x" color={colors.ash} /></Pressable> : null}
      </View>
      {field === 'pickup' ? (
        <Pressable onPress={useMyLocation} style={{ flexDirection: 'row', alignItems: 'center', gap: space.md, paddingVertical: space.lg }} accessibilityRole="button">
          {locating ? <ActivityIndicator color={colors.gold} /> : <Icon name="crosshair" color={colors.gold} />}
          <Body>{locating ? 'Finding you…' : 'Use my current location'}</Body>
        </Pressable>
      ) : null}
      {error ? <Small color={colors.danger} style={{ marginVertical: space.sm }}>{error}</Small> : null}
      <FlatList
        data={results}
        keyExtractor={(r, i) => `${r.place_id ?? r.address}-${i}`}
        keyboardShouldPersistTaps="handled"
        renderItem={({ item }) => (
          <Pressable onPress={() => choose(item)} style={({ pressed }) => [{ paddingVertical: 14, borderBottomWidth: 0.5, borderColor: colors.line }, pressed && { opacity: 0.6 }]} accessibilityRole="button">
            <Body>{item.label ?? item.address}</Body>
            {item.label ? <Muted numberOfLines={1}>{item.address}</Muted> : null}
          </Pressable>
        )}
        ListEmptyComponent={q.trim().length >= 3 && !busy ? <Muted style={{ marginTop: space.lg }}>No matches yet. Try a street name and suburb.</Muted> : null}
      />
    </Screen>
  );
}
