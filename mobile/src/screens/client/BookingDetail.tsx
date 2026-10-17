import React, { useState } from 'react';
import { Alert, Image, Linking, Pressable, Share, StyleSheet, TextInput, View } from 'react-native';
import { api, errorMessage } from '../../api/client';
import type { Booking } from '../../api/types';
import { useLoader } from '../../lib/hooks';
import { fmt, rands } from '../../lib/format';
import { MapView, MapPoint } from '../../map/MapView';
import { useSession } from '../../state/session';
import { colors, fonts, radius, space } from '../../theme';
import { Banner, Body, Button, Card, Divider, Gap, Icon, Label, Loading, Money, Muted, Row, Screen, Section, Small, StatusPill, Title } from '../../components/ui';
import { statusTone } from './Bookings';

function headline(b: Booking): [string, string] {
  const name = b.operators[0]?.first_name ?? 'Your operator';
  switch (b.status) {
    case 'pending_approval': return ['We are reviewing your request', 'Our team confirms new bookings quickly, usually within the hour.'];
    case 'quote_requested': return ['We are preparing your quote', 'A planner is reviewing your requirements and will send a firm price.'];
    case 'quoted': return ['Your quote is ready', 'Your booking is confirmed the moment you accept.'];
    case 'confirmed': return ['You are booked', 'We will assign your operator ahead of time and send you their details and your arrival PIN.'];
    case 'searching': return ['Finding your operator', 'We are offering your job to the nearest operators who meet your requirements.'];
    case 'assigned': return [`${name} is assigned`, 'You will see them on the map once they set off.'];
    case 'en_route': return [`${name} is on the way`, b.eta_minutes ? `Arriving in about ${b.eta_minutes} min.` : 'Follow them on the map.'];
    case 'arrived': return [`${name} has arrived`, 'Ask for your PIN before you get in.'];
    case 'in_progress': return ['Under way', 'Our control room is monitoring your trip.'];
    case 'completed': return ['Completed', 'Thank you. Your receipt has been emailed to you.'];
    case 'cancelled': return ['Cancelled', b.payment.cancellation_fee ? `A cancellation fee of ${rands(b.payment.cancellation_fee)} applied.` : 'No fee applied.'];
    case 'no_show': return ['Recorded as a no-show', 'Your operator waited but could not find you. If this is wrong, please contact us.'];
    case 'expired': return ['No operator was available', 'Nothing has been charged. Try booking ahead, or call the control room.'];
    default: return [b.status_label, ''];
  }
}

export default function BookingDetail({ navigation, route }: any) {
  const reference: string = route.params.reference;
  const { config } = useSession();
  const [busy, setBusy] = useState<string | null>(null);
  const [score, setScore] = useState(0);
  const [comment, setComment] = useState('');
  const { data: b, error, setData, reload, refreshing } = useLoader(
    async () => (await api.get<{ data: Booking }>(`bookings/${reference}`)).data,
    [reference],
    5000,
  );

  if (!b) return error ? <Screen><Banner tone="danger" text={error} /></Screen> : <Loading />;
  const [title, sub] = headline(b);
  const live = ['searching', 'assigned', 'en_route', 'arrived', 'in_progress'].includes(b.status);
  const points: MapPoint[] = [
    { ...b.pickup, kind: 'place', label: 'A' },
    ...b.stops.map((s, i) => ({ ...s, kind: 'place' as const, label: i + 1 })),
    ...(b.dropoff ? [{ ...b.dropoff, kind: 'place' as const, label: 'B' }] : []),
    ...b.operators.filter((o) => o.location).map((o) => ({ lat: o.location!.lat, lng: o.location!.lng, kind: 'operator' as const, label: (o.call_sign ?? '').replace(/^\D+/, '') || o.first_name[0] })),
  ];

  async function act(key: string, fn: () => Promise<any>, success?: string) {
    setBusy(key);
    try {
      const r = await fn();
      if (r?.data?.reference) setData(r.data);
      else if (r?.booking?.reference) setData(r.booking);
      else reload();
      if (success) Alert.alert(success);
    } catch (e) {
      Alert.alert('Not done', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  async function cancel() {
    setBusy('cancel');
    try {
      const fee = await api.get<{ fee: number; reason: string; cancellable: boolean }>(`bookings/${reference}/cancellation-fee`);
      setBusy(null);
      Alert.alert('Cancel this booking?', `${fee.reason}\n\nCancellation fee: ${fee.fee > 0 ? rands(fee.fee, true) : 'none'}`, [
        { text: 'Keep it', style: 'cancel' },
        { text: 'Cancel booking', style: 'destructive', onPress: () => act('cancel', () => api.post(`bookings/${reference}/cancel`, { reason: 'Cancelled in app' })) },
      ]);
    } catch (e) {
      setBusy(null);
      Alert.alert('Not done', errorMessage(e));
    }
  }

  async function share() {
    setBusy('share');
    try {
      const r = await api.post<{ url: string; message: string }>(`bookings/${reference}/share`, { label: 'app' });
      await Share.share({ message: r.message, url: r.url });
    } catch (e) {
      Alert.alert('Could not share', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  const control = config?.brand.control_room_phone;

  return (
    <Screen edges={['bottom']} onRefresh={reload} refreshing={refreshing} padded={false}>
      {(live || b.pickup.lat) && !b.is_closed ? <MapView data={{ points, route: true, follow: b.operators.some((o) => o.location) }} style={{ height: 260 }} /> : null}
      <View style={{ paddingHorizontal: space.lg, paddingTop: space.lg, paddingBottom: space.xxl }}>
        <Row style={{ justifyContent: 'space-between' }}>
          <Small>{b.service.name}, ref {b.reference}</Small>
          <StatusPill label={b.status_label} tone={statusTone(b.status)} />
        </Row>
        <Title style={{ marginTop: space.sm }}>{title}</Title>
        {sub ? <Muted style={{ marginTop: 4 }}>{sub}</Muted> : null}

        {b.can.sos ? (
          <Pressable onPress={() => navigation.navigate('Sos', { reference })} style={styles.sos} accessibilityRole="button" accessibilityLabel="SOS: alert the control room">
            <Icon name="alert-octagon" color="#f3c3b8" size={20} />
            <Label color="#f3c3b8">SOS: alert the control room</Label>
          </Pressable>
        ) : null}

        {b.can.accept_quote ? (
          <Card tone="gold" style={{ marginTop: space.lg }}>
            <Small>Your quote</Small>
            <Money>{rands(b.quote.total)}</Money>
            {b.quote.custom_note ? <Muted style={{ marginTop: space.sm }}>{b.quote.custom_note}</Muted> : null}
            <Gap h={space.md} />
            <Button title="Accept and confirm" loading={busy === 'accept'} onPress={() => act('accept', () => api.post(`bookings/${reference}/accept-quote`), 'Quote accepted. Your booking is confirmed.')} />
          </Card>
        ) : null}

        {b.reconfirm.required ? (
          <Card tone="gold" style={{ marginTop: space.lg }}>
            <Label>Please reconfirm your booking</Label>
            <Muted style={{ marginTop: 4 }}>Let us know you still need us{b.reconfirm.due_at ? ` by ${fmt.dateTime(b.reconfirm.due_at)}` : ''}. Unconfirmed bookings may be released.</Muted>
            <Gap h={space.md} />
            <Button title="Yes, I still need this" loading={busy === 'reconfirm'} onPress={() => act('reconfirm', () => api.post(`bookings/${reference}/reconfirm`))} />
          </Card>
        ) : null}

        {b.operators.length ? (
          <Card style={{ marginTop: space.lg, padding: 0 }}>
            {b.operators.map((o, i) => (
              <View key={o.assignment_id}>
                {i ? <Divider /> : null}
                <Row style={{ padding: space.lg }}>
                  {o.photo_url ? <Image source={{ uri: o.photo_url }} style={styles.avatar} /> : <View style={[styles.avatar, { alignItems: 'center', justifyContent: 'center', backgroundColor: colors.graphite }]}><Title style={{ fontSize: 24, color: colors.goldLight }}>{o.first_name[0]}</Title></View>}
                  <View style={{ flex: 1 }}>
                    <Label>{o.first_name} <Muted>{o.role_label}</Muted></Label>
                    {o.vehicle ? <Muted>{o.vehicle.label}</Muted> : null}
                    <Small>{o.status_label}</Small>
                  </View>
                  {o.vehicle ? <View style={styles.plate}><Label style={{ letterSpacing: 1 }}>{o.vehicle.registration}</Label></View> : null}
                </Row>
              </View>
            ))}
            {b.arrival_pin ? (
              <>
                <Divider />
                <View style={{ padding: space.lg }}>
                  <Small>Your arrival PIN</Small>
                  <Body style={{ fontFamily: fonts.display, fontSize: 52, lineHeight: 58, color: colors.goldLight, letterSpacing: 10 }} accessibilityLabel={`Arrival PIN ${b.arrival_pin.split('').join(' ')}`}>{b.arrival_pin}</Body>
                  <Muted>{b.pin_verified ? 'Confirmed by your operator.' : 'Your operator will say this PIN when they meet you. Only get in once they have, and never read it out first.'}</Muted>
                </View>
              </>
            ) : null}
            {b.can.chat || b.can.call ? (
              <>
                <Divider />
                <Row style={{ padding: space.md }}>
                  {b.can.chat ? <Button small variant="line" icon="message-square" title="Message" onPress={() => navigation.navigate('Chat', { reference })} style={{ flex: 1 }} /> : null}
                  {b.can.call ? <Button small variant="line" icon="phone" title="Call privately" loading={busy === 'call'} onPress={() => act('call', () => api.post(`bookings/${reference}/call`), 'We are calling your phone now and will connect you to your operator.')} style={{ flex: 1 }} /> : null}
                </Row>
              </>
            ) : null}
          </Card>
        ) : null}

        {b.can.rate ? (
          <Card style={{ marginTop: space.lg }}>
            <Label>How was your experience?</Label>
            <Row gap={4} style={{ marginVertical: space.md }}>
              {[1, 2, 3, 4, 5].map((n) => (
                <Pressable key={n} onPress={() => setScore(n)} hitSlop={6} accessibilityRole="button" accessibilityLabel={`${n} out of 5`}>
                  <Icon name="star" size={32} color={score >= n ? colors.gold : colors.smoke} />
                </Pressable>
              ))}
            </Row>
            <TextInput value={comment} onChangeText={setComment} placeholder="Anything we should know? (optional)" placeholderTextColor={colors.ash} multiline style={styles.input} />
            <Gap h={space.md} />
            <Button title="Send feedback" disabled={!score} loading={busy === 'rate'} onPress={() => act('rate', () => api.post(`bookings/${reference}/rate`, { score, comment: comment || null }), 'Thank you for your feedback.')} />
          </Card>
        ) : null}

        <Section title="Details">
          <Card>
            <Small>When</Small>
            <Body>{b.mode === 'on_demand' ? `On demand, ${fmt.dateTime(b.starts_at)}` : b.shifts.length > 1 ? `${b.shifts.length} shifts, ${fmt.dayShort(b.shifts[0].starts_at)} to ${fmt.dayShort(b.shifts[b.shifts.length - 1].ends_at)}` : fmt.dateTime(b.starts_at)}</Body>
            {b.shifts.length <= 1 ? <Muted>Until about {fmt.time(b.ends_at)} ({fmt.minutes(b.duration_minutes)})</Muted> : null}
            <Gap h={space.md} />
            <Small>{b.service.includes_transport ? 'Pickup' : 'Meeting point'}</Small>
            <Body>{b.pickup.address}</Body>
            {b.stops.map((s, i) => <View key={i} style={{ marginTop: space.sm }}><Small>Stop {i + 1}</Small><Body>{s.address}</Body></View>)}
            {b.dropoff ? <View style={{ marginTop: space.sm }}><Small>Drop-off</Small><Body>{b.dropoff.address}</Body></View> : null}
            {b.principal ? <View style={{ marginTop: space.sm }}><Small>For</Small><Body>{b.principal.name}</Body></View> : null}
            {b.requirements.length ? <View style={{ marginTop: space.sm }}><Small>Requirements</Small><Body>{b.requirements.join(', ')}</Body></View> : null}
            {b.notes ? <View style={{ marginTop: space.sm }}><Small>Notes</Small><Body>{b.notes}</Body></View> : null}
            <Divider style={{ marginVertical: space.md }} />
            <Row style={{ justifyContent: 'space-between' }}>
              <Small>{b.quote.final_total ? 'Total charged' : b.quote.awaiting_quote ? 'Price' : 'Quoted price'}</Small>
              <Money style={{ fontSize: 28 }}>{b.quote.awaiting_quote ? 'To follow' : rands(b.quote.final_total ?? b.quote.total)}</Money>
            </Row>
            <Small>{b.payment.method === 'eft' ? 'Monthly invoice (EFT)' : b.payment.method === 'card_machine' ? 'Card machine at the end' : b.payment.card ?? 'Card on file'}</Small>
          </Card>
        </Section>

        {b.timeline.length ? (
          <Section title="Timeline">
            {b.timeline.map((e, i) => (
              <Row key={i} style={{ alignItems: 'flex-start', marginBottom: space.md }}>
                <View style={styles.dot} />
                <View style={{ flex: 1 }}><Body>{e.summary}</Body><Small>{fmt.dateTime(e.at)}</Small></View>
              </Row>
            ))}
          </Section>
        ) : null}

        <Gap h={space.xl} />
        {b.can.share ? <Button variant="line" icon="share-2" title="Share trip with someone" loading={busy === 'share'} onPress={share} /> : null}
        {control ? <Button variant="ghost" icon="headphones" title="Call the control room" onPress={() => Linking.openURL(`tel:${control.replace(/\s+/g, '')}`)} /> : null}
        {b.can.cancel ? <Button variant="ghost" title="Cancel booking" loading={busy === 'cancel'} onPress={cancel} /> : null}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  sos: { marginTop: space.lg, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: space.sm, minHeight: 56, borderRadius: radius.md, borderWidth: 1, borderColor: 'rgba(229,130,111,0.6)', backgroundColor: colors.dangerWash },
  avatar: { width: 52, height: 52, borderRadius: 26 },
  plate: { borderWidth: 1, borderColor: colors.lineStrong, borderRadius: 6, paddingHorizontal: 10, paddingVertical: 6, backgroundColor: colors.night },
  input: { minHeight: 80, borderRadius: radius.md, backgroundColor: colors.graphite, color: colors.bone, padding: space.md, fontFamily: fonts.body, fontSize: 15, textAlignVertical: 'top' },
  dot: { width: 9, height: 9, borderRadius: 5, backgroundColor: colors.gold, marginTop: 7 },
});
