import React, { useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { api, errorMessage } from '../../api/client';
import type { Quote } from '../../api/types';
import { ChoiceRow, DayStrip, TimeSlots } from '../../components/TimePicker';
import { fmt, rands, saNow } from '../../lib/format';
import { toPayload, useDraft } from '../../state/draft';
import { useFeature, useSession } from '../../state/session';
import { colors, radius, space } from '../../theme';
import { Body, Button, Check, Chip, Field, Gap, Icon, Label, Money, Muted, Row, Screen, Section, Segmented, Small, Stepper, Title } from '../../components/ui';

const DURATIONS = [60, 90, 120, 180, 240, 300, 360, 480, 600, 720];
const WEEKDAYS = [[1, 'Mon'], [2, 'Tue'], [3, 'Wed'], [4, 'Thu'], [5, 'Fri'], [6, 'Sat'], [7, 'Sun']] as const;

export default function BookDetails({ navigation }: any) {
  const insets = useSafeAreaInsets();
  const { draft, set } = useDraft();
  const { config, user } = useSession();
  const teams = useFeature('team_bookings');
  const multiStop = useFeature('multi_stop');
  const recurring = useFeature('recurring');
  const s = draft.service!;
  const multi = s.structure === 'multi_day';
  const [quote, setQuote] = useState<Quote | null>(null);
  const [route, setRoute] = useState<{ km: number; minutes: number } | null>(null);
  const [minMinutes, setMinMinutes] = useState(s.min_duration_minutes);
  const [quoteError, setQuoteError] = useState<string | null>(null);
  const [pricing, setPricing] = useState(false);
  const seq = useRef(0);

  const notice = Math.max(s.min_notice_minutes, (multi ? config?.booking.notice_minutes_team : config?.booking.notice_minutes) ?? 120);
  const earliest = useMemo(() => {
    const d = saNow();
    d.setMinutes(d.getMinutes() + notice);
    return d;
  }, [notice]);
  const canNow = s.allows_on_demand;
  const canLater = s.allows_advance;

  useEffect(() => {
    if (draft.mode === 'on_demand' && !canNow) set({ mode: 'advance' });
    if (draft.mode === 'advance' && !canLater && canNow) set({ mode: 'on_demand' });
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [s.id]);

  const payloadKey = JSON.stringify(toPayload({ ...draft, notes: '', principalName: '', principalPhone: '' }));
  useEffect(() => {
    if (!draft.pickup) {
      setQuote(null);
      setQuoteError(s.includes_transport ? 'Add a pickup point to see your price.' : 'Add a meeting point to see your price.');
      return undefined;
    }
    const id = ++seq.current;
    setPricing(true);
    const t = setTimeout(async () => {
      try {
        const r = await api.post('quotes', toPayload(draft));
        if (id !== seq.current) return;
        setQuote(r.quote);
        setRoute(r.route);
        setMinMinutes(r.minimum_minutes);
        setQuoteError(null);
        if (!multi && draft.mode === 'advance' && draft.duration < r.minimum_minutes) set({ duration: r.minimum_minutes });
      } catch (e) {
        if (id !== seq.current) return;
        setQuote(null);
        setQuoteError(errorMessage(e));
      } finally {
        if (id === seq.current) setPricing(false);
      }
    }, 400);
    return () => clearTimeout(t);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [payloadKey]);

  const filters = (config?.filters ?? []).filter((f) => f.applies_to !== 'vehicle' || s.includes_transport);
  const toggle = (arr: number[], id: number) => (arr.includes(id) ? arr.filter((x) => x !== id) : [...arr, id]);
  const maxGuards = teams ? 20 : Math.max(1, s.default_guards);

  return (
    <View style={{ flex: 1, backgroundColor: colors.night }}>
      <Screen edges={[]} contentStyle={{ paddingBottom: 140 }}>
        <Title>{s.name}</Title>
        {s.tagline ? <Muted style={{ marginTop: 4 }}>{s.tagline}</Muted> : null}

        <Section title="When">
          {canNow && canLater && !multi ? (
            <><Segmented value={draft.mode} onChange={(mode) => set({ mode })} options={[{ value: 'on_demand', label: 'As soon as possible' }, { value: 'advance', label: 'Schedule' }]} /><Gap h={space.lg} /></>
          ) : null}
          {draft.mode === 'on_demand' ? (
            <Muted>We will send the nearest available operator who meets your requirements. You will see them on the map as soon as they accept.</Muted>
          ) : multi ? (
            <>
              {recurring ? <><Segmented value={draft.schedule.type} onChange={(type) => set({ schedule: { ...draft.schedule, type } })} options={[{ value: 'multi_day', label: 'Every day' }, { value: 'recurring', label: 'Selected days' }]} /><Gap h={space.lg} /></> : null}
              <Label style={{ marginBottom: space.sm }}>First day</Label>
              <DayStrip value={new Date(draft.schedule.start_date + 'T12:00:00')} daysAhead={config?.booking.max_days_ahead ?? 180} minDate={earliest} onChange={(d) => set({ schedule: { ...draft.schedule, start_date: fmt.ymd(d), end_date: draft.schedule.end_date < fmt.ymd(d) ? fmt.ymd(d) : draft.schedule.end_date } })} />
              <Gap h={space.lg} />
              <Label style={{ marginBottom: space.sm }}>Last day</Label>
              <DayStrip value={new Date(draft.schedule.end_date + 'T12:00:00')} daysAhead={config?.booking.max_days_ahead ?? 180} minDate={new Date(draft.schedule.start_date + 'T00:00:00')} onChange={(d) => set({ schedule: { ...draft.schedule, end_date: fmt.ymd(d) } })} />
              <Gap h={space.lg} />
              <Row>
                <Field label="Daily start" value={draft.schedule.start_time} onChangeText={(v) => set({ schedule: { ...draft.schedule, start_time: v } })} placeholder="08:00" keyboardType="numbers-and-punctuation" style={{ flex: 1 }} />
                <Field label="Daily finish" value={draft.schedule.end_time} onChangeText={(v) => set({ schedule: { ...draft.schedule, end_time: v } })} placeholder="18:00" keyboardType="numbers-and-punctuation" style={{ flex: 1 }} />
              </Row>
              {draft.schedule.type === 'recurring' ? (
                <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: space.sm }}>
                  {WEEKDAYS.map(([n, l]) => <Chip key={n} label={l} on={draft.schedule.weekdays.includes(n)} onPress={() => set({ schedule: { ...draft.schedule, weekdays: toggle(draft.schedule.weekdays, n) } })} />)}
                </View>
              ) : null}
            </>
          ) : (
            <>
              <DayStrip value={draft.start} daysAhead={config?.booking.max_days_ahead ?? 180} onChange={(d) => set({ start: d < earliest ? new Date(earliest.getTime() + (30 - (earliest.getMinutes() % 30)) * 60000) : d })} />
              <Gap h={space.md} />
              <TimeSlots value={draft.start} onChange={(d) => set({ start: d })} earliest={earliest} />
              <Gap h={space.lg} />
              <Label style={{ marginBottom: space.sm }}>For how long</Label>
              <ChoiceRow value={draft.duration} onChange={(duration) => set({ duration })} options={DURATIONS.filter((m) => m >= minMinutes || m === draft.duration).map((m) => ({ value: m, label: fmt.minutes(m).replace(' hours', 'h').replace(' hour', 'h').replace(' min', 'm') }))} />
              <Small style={{ marginTop: space.sm }}>Book at least {fmt.minutes(notice)} ahead for this service. Extra time is charged by the hour.</Small>
            </>
          )}
        </Section>

        <Section title={s.includes_transport ? 'Where' : 'Where should your officer meet you?'}>
          <PlaceButton icon="map-pin" label={s.includes_transport ? 'Pickup' : 'Meeting point'} value={draft.pickup?.address} onPress={() => navigation.navigate('PlaceSearch', { field: 'pickup' })} />
          {s.includes_transport ? (
            <>
              {draft.stops.map((st, i) => (
                <View key={i}>
                  <PlaceButton icon="more-vertical" label={`Stop ${i + 1}`} value={st.address} onPress={() => navigation.navigate('PlaceSearch', { field: 'stop', index: i })} onClear={() => set({ stops: draft.stops.filter((_, j) => j !== i) })} />
                  <View style={{ marginLeft: 36, marginBottom: space.sm }}>
                    <ChoiceRow value={st.wait_minutes} onChange={(w) => set({ stops: draft.stops.map((x, j) => (j === i ? { ...x, wait_minutes: w } : x)) })} options={[0, 15, 30, 60, 120].map((w) => ({ value: w, label: w ? `Wait ${w}m` : 'No wait' }))} />
                  </View>
                </View>
              ))}
              {multiStop && draft.stops.length < 8 ? (
                <Pressable onPress={() => navigation.navigate('PlaceSearch', { field: 'stop', index: draft.stops.length })} style={{ paddingVertical: 10 }} accessibilityRole="button">
                  <Body color={colors.gold}>Add a stop</Body>
                </Pressable>
              ) : null}
              <PlaceButton icon="flag" label="Drop-off (optional for hourly bookings)" value={draft.dropoff?.address} onPress={() => navigation.navigate('PlaceSearch', { field: 'dropoff' })} onClear={draft.dropoff ? () => set({ dropoff: null }) : undefined} />
              {route && route.km > 0 ? <Small>About {route.km} km, {route.minutes} minutes' driving.</Small> : null}
            </>
          ) : null}
        </Section>

        <Section title="Requirements">
          <Row style={{ flexWrap: 'wrap', gap: space.xl }}>
            {s.includes_protection || !s.includes_transport ? <Stepper label="Officers" value={draft.guards} min={s.includes_transport ? 0 : 1} max={maxGuards} onChange={(guards) => set({ guards })} /> : null}
            {s.includes_transport && teams ? <Stepper label="Vehicles" value={draft.vehicles} min={1} max={10} onChange={(vehicles) => set({ vehicles })} /> : null}
            {s.includes_transport ? <Stepper label="Passengers" value={draft.passengers} min={1} max={7} onChange={(passengers) => set({ passengers })} /> : null}
          </Row>
          {filters.length ? (
            <>
              <Gap />
              <Label style={{ marginBottom: space.sm }}>Request specific capabilities</Label>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: space.sm }}>
                {filters.map((f) => (
                  <Chip key={f.id} label={f.label} on={draft.featureIds.includes(f.id)} onPress={() => set({ featureIds: toggle(draft.featureIds, f.id) })}
                    note={f.surcharge ? `+${rands(f.surcharge.amount)}${f.surcharge.type === 'per_hour' ? '/h' : ''}` : undefined} />
                ))}
              </View>
            </>
          ) : null}
          {s.extras.length ? (
            <>
              <Gap />
              <Label style={{ marginBottom: space.sm }}>Add-ons</Label>
              {s.extras.map((e) => (
                <Check key={e.id} on={draft.extraIds.includes(e.id)} onPress={() => set({ extraIds: toggle(draft.extraIds, e.id) })}
                  label={<View><Body>{e.name} <Body color={colors.ash}>{e.pricing === 'percent' ? `${e.amount}%` : `${rands(e.amount)}${e.pricing === 'per_hour' ? '/h' : ''}`}</Body></Body>{e.description ? <Small>{e.description}</Small> : null}</View>} />
              ))}
            </>
          ) : null}
        </Section>

        <Section title="Who">
          <Check on={draft.forSomeoneElse} onPress={() => set({ forSomeoneElse: !draft.forSomeoneElse })} label="I'm booking for someone else" />
          {draft.forSomeoneElse ? (
            <>
              <Gap h={space.md} />
              <Field label="Their name" value={draft.principalName} onChangeText={(principalName) => set({ principalName })} />
              <Field label="Their mobile number" value={draft.principalPhone} onChangeText={(principalPhone) => set({ principalPhone })} keyboardType="phone-pad" hint="They get the operator's details, the PIN and a tracking link by SMS." />
            </>
          ) : null}
          <Gap h={space.md} />
          <Field label="Notes for your operator" value={draft.notes} onChangeText={(notes) => set({ notes })} multiline placeholder="Gate codes, where to wait, luggage, anything that helps." />
        </Section>
      </Screen>

      <View style={[styles.footer, { paddingBottom: insets.bottom + space.md }]}>
        <View style={{ flex: 1 }}>
          {quote ? (
            <>
              <Small>{quote.custom_quote ? 'Estimate, final quote to follow' : 'Total'}</Small>
              <Row gap={space.sm}><Money>{rands(quote.total)}</Money>{pricing ? <ActivityIndicator color={colors.gold} size="small" /> : null}</Row>
            </>
          ) : pricing ? <ActivityIndicator color={colors.gold} /> : <Small color={colors.mist} numberOfLines={3}>{quoteError}</Small>}
        </View>
        <Button title="Review" onPress={() => navigation.navigate('BookReview', { quote })} disabled={!quote || pricing || (draft.forSomeoneElse && (!draft.principalName || !draft.principalPhone))} style={{ minWidth: 130 }} />
      </View>
    </View>
  );
}

function PlaceButton({ icon, label, value, onPress, onClear }: { icon: any; label: string; value?: string | null; onPress: () => void; onClear?: () => void }) {
  return (
    <Pressable onPress={onPress} style={styles.place} accessibilityRole="button" accessibilityLabel={`${label}: ${value ?? 'not set'}`}>
      <Icon name={icon} color={colors.gold} />
      <View style={{ flex: 1 }}>
        <Small>{label}</Small>
        <Body numberOfLines={2} color={value ? colors.bone : colors.ash}>{value ?? 'Search for an address'}</Body>
      </View>
      {onClear ? <Pressable onPress={onClear} hitSlop={10} accessibilityLabel={`Remove ${label}`}><Icon name="x" color={colors.ash} /></Pressable> : <Icon name="chevron-right" color={colors.ash} />}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  footer: { position: 'absolute', left: 0, right: 0, bottom: 0, flexDirection: 'row', alignItems: 'center', gap: space.lg, paddingHorizontal: space.lg, paddingTop: space.md, backgroundColor: 'rgba(7,7,7,0.97)', borderTopWidth: StyleSheet.hairlineWidth, borderColor: colors.lineStrong },
  place: { flexDirection: 'row', alignItems: 'center', gap: space.md, padding: space.md, borderRadius: radius.md, backgroundColor: colors.graphite, marginBottom: space.sm, minHeight: 60 },
});
