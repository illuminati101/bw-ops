import React, { useEffect, useState } from 'react';
import { Alert, Linking, Platform, Pressable, StyleSheet, View } from 'react-native';
import * as Location from 'expo-location';
import { api, errorMessage } from '../../api/client';
import type { Job as JobT } from '../../api/types';
import { useLoader } from '../../lib/hooks';
import { fmt, rands } from '../../lib/format';
import { MapView, MapPoint } from '../../map/MapView';
import { useSession } from '../../state/session';
import { colors, fonts, radius, space } from '../../theme';
import { Banner, Body, Button, Card, Divider, Field, Gap, Icon, Label, Loading, Money, Muted, Row, Screen, Section, Small, StatusPill, Title } from '../../components/ui';

function navigateTo(lat: number, lng: number) {
  const google = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`;
  const options: { text: string; onPress: () => void }[] = [
    { text: 'Google Maps', onPress: () => Linking.openURL(google) },
    { text: 'Waze', onPress: () => Linking.openURL(`https://waze.com/ul?ll=${lat},${lng}&navigate=yes`) },
  ];
  if (Platform.OS === 'ios') options.push({ text: 'Apple Maps', onPress: () => Linking.openURL(`http://maps.apple.com/?daddr=${lat},${lng}`) });
  Alert.alert('Navigate with', undefined, [...options, { text: 'Cancel', onPress: () => {} }]);
}

export default function Job({ route, navigation }: any) {
  const id: number = route.params.id;
  const { config } = useSession();
  const [busy, setBusy] = useState<string | null>(null);
  const [pin, setPin] = useState('');
  const [pinError, setPinError] = useState<string | undefined>();
  const [km, setKm] = useState('');
  const [override, setOverride] = useState<string | null>(null);
  const [me, setMe] = useState<{ lat: number; lng: number } | null>(null);
  const { data, setData, reload, refreshing, error } = useLoader(async () => (await api.get<{ job: JobT }>(`crew/jobs/${id}`)).job, [id], 6000);

  useEffect(() => {
    let sub: Location.LocationSubscription | null = null;
    Location.getForegroundPermissionsAsync().then(async (p) => {
      if (p.status === 'granted') sub = await Location.watchPositionAsync({ accuracy: Location.Accuracy.High, distanceInterval: 20 }, (l) => setMe({ lat: l.coords.latitude, lng: l.coords.longitude }));
    });
    return () => sub?.remove();
  }, []);

  if (!data) return error ? <Screen><Banner tone="danger" text={error} /></Screen> : <Loading />;
  const j = data;

  async function action(name: string, extra: Record<string, any> = {}) {
    setBusy(name);
    setPinError(undefined);
    try {
      const r = await api.post<{ job: JobT }>(`crew/jobs/${id}/action`, { action: name, ...extra });
      setData(r.job);
      if (name === 'complete' || name === 'no_show') {
        Alert.alert(name === 'complete' ? 'Job complete' : 'No-show recorded', name === 'complete' ? 'Thank you. The client has been charged and the control room notified.' : 'The control room has been notified.');
        navigation.goBack();
      }
    } catch (e) {
      const msg = errorMessage(e);
      if (name === 'start') setPinError(msg);
      else Alert.alert('Not done', msg);
    } finally {
      setBusy(null);
    }
  }

  const points: MapPoint[] = [
    ...(j.pickup ? [{ ...j.pickup, kind: 'place' as const, label: 'A' }] : []),
    ...(j.stops ?? []).map((s, i) => ({ ...s, kind: 'place' as const, label: i + 1 })),
    ...(j.dropoff ? [{ ...j.dropoff, kind: 'place' as const, label: 'B' }] : []),
    ...(me ? [{ ...me, kind: 'me' as const }] : []),
  ];
  const control = config?.brand.control_room_phone?.replace(/\s+/g, '');
  const can = j.can;
  const target = j.status === 'on_job' && j.dropoff ? j.dropoff : j.pickup;

  return (
    <Screen edges={['bottom']} padded={false} onRefresh={reload} refreshing={refreshing}>
      {j.live ? <MapView data={{ points, route: true }} style={{ height: 240 }} /> : null}
      <View style={{ padding: space.lg, paddingBottom: space.xxl }}>
        <Row style={{ justifyContent: 'space-between' }}>
          <Small>{j.reference}, {j.role_label}</Small>
          <StatusPill label={j.status_label} tone="gold" />
        </Row>
        <Title style={{ marginTop: space.sm }}>{j.service}</Title>
        <Muted>{j.mode === 'on_demand' ? 'On demand' : `${fmt.relativeDay(j.starts_at)}, ${fmt.time(j.starts_at)} to ${fmt.time(j.ends_at)}`}</Muted>

        {!j.live ? <><Gap /><Banner tone="plain" text="This job has ended. Details are no longer shown on your device." /></> : null}

        {j.live && j.principal ? (
          <Card style={{ marginTop: space.lg }}>
            <Small>Client</Small>
            <Label>{j.principal.name}</Label>
            <Muted>{j.principal.passengers} passenger{j.principal.passengers === 1 ? '' : 's'}</Muted>
            {j.pickup ? (
              <>
                <Gap h={space.md} />
                <Small>{j.dropoff ? 'Pickup' : 'Meeting point'}</Small>
                <Body>{j.pickup.address}</Body>
              </>
            ) : null}
            {(j.stops ?? []).map((s, i) => <View key={i} style={{ marginTop: space.sm }}><Small>Stop {i + 1}{s.wait_minutes ? `, wait ${s.wait_minutes} min` : ''}</Small><Body>{s.address}</Body></View>)}
            {j.dropoff ? <View style={{ marginTop: space.sm }}><Small>Drop-off</Small><Body>{j.dropoff.address}</Body></View> : null}
            {target ? <><Gap h={space.md} /><Button small variant="line" icon="navigation" title={j.status === 'on_job' && j.dropoff ? 'Navigate to drop-off' : 'Navigate to pickup'} onPress={() => navigateTo(target.lat, target.lng)} /></> : null}
          </Card>
        ) : null}

        {j.live && (j.requirements?.length || j.notes || j.team?.length || j.vehicle) ? (
          <Card style={{ marginTop: space.md }}>
            {j.vehicle ? <><Small>Vehicle</Small><Body>{j.vehicle}</Body></> : null}
            {j.requirements?.length ? <View style={{ marginTop: space.sm }}><Small>Requirements</Small><Body>{j.requirements.join(', ')}</Body></View> : null}
            {j.team?.length ? <View style={{ marginTop: space.sm }}><Small>Team</Small>{j.team.map((t, i) => <Body key={i}>{t.name} ({t.call_sign}), {t.role}</Body>)}</View> : null}
            {j.notes ? <View style={{ marginTop: space.sm }}><Small>Notes</Small><Body>{j.notes}</Body></View> : null}
          </Card>
        ) : null}

        {j.live && can ? (
          <Section>
            {can.accept ? <Button title="Accept this job" loading={busy === 'accept'} onPress={async () => { setBusy('accept'); try { const r = await api.post<{ job: JobT }>(`crew/assignments/${id}/respond`, { accept: true }); setData(r.job); } catch (e) { Alert.alert('Not done', errorMessage(e)); } finally { setBusy(null); } }} /> : null}
            {can.en_route ? <Button title="I'm on my way" icon="navigation" loading={busy === 'en_route'} onPress={() => action('en_route')} /> : null}
            {can.arrived && j.status !== 'arrived' ? <><Gap h={space.sm} /><Button variant={can.en_route ? 'line' : 'gold'} title="I've arrived" icon="map-pin" loading={busy === 'arrived'} onPress={() => action('arrived')} /></> : null}
            {can.start ? (
              <Card style={{ marginTop: space.md }} tone="gold">
                {j.pin_required ? (
                  <>
                    <Label>Ask the client for their arrival PIN</Label>
                    <Muted style={{ marginTop: 4 }}>Never say a PIN first. Start only when the client gives you the correct one.</Muted>
                    <Gap h={space.md} />
                    <Field value={pin} onChangeText={(t) => setPin(t.replace(/\D/g, '').slice(0, 4))} keyboardType="number-pad" maxLength={4} placeholder="4-digit PIN" error={pinError} style={{ marginBottom: space.sm }} />
                    <Button title="Check PIN and start" loading={busy === 'start'} disabled={pin.length !== 4} onPress={() => action('start', { pin })} />
                    {override === null ? (
                      <Button variant="ghost" small title="Client can't give a PIN?" onPress={() => setOverride('')} />
                    ) : (
                      <>
                        <Gap h={space.md} />
                        <Muted>Call the control room first. They can approve a start without the PIN; they are alerted when you do.</Muted>
                        <Field label="Reason (who approved it)" value={override} onChangeText={setOverride} style={{ marginTop: space.sm, marginBottom: space.sm }} />
                        <Button variant="line" title="Start without PIN" disabled={override.trim().length < 5} loading={busy === 'start'} onPress={() => action('start', { pin_override_reason: override.trim() })} />
                      </>
                    )}
                  </>
                ) : (
                  <Button title="Start job" loading={busy === 'start'} onPress={() => action('start')} />
                )}
              </Card>
            ) : null}
            {can.complete ? (
              <Card style={{ marginTop: space.md }}>
                <Label>Finish the job</Label>
                {j.dropoff ? <Field label="Odometer distance driven, km (optional)" value={km} onChangeText={setKm} keyboardType="decimal-pad" style={{ marginTop: space.md }} /> : <Gap h={space.md} />}
                {j.card_machine_due ? <Banner text={`Collect ${rands(j.card_machine_due, true)} on the card machine, then confirm below.`} /> : null}
                {j.card_machine_due ? <><Gap h={space.sm} /><Button variant="line" title="Card machine payment taken" loading={busy === 'card_machine_paid'} onPress={() => action('card_machine_paid')} /><Gap h={space.sm} /></> : null}
                <Button haptic title="Complete job" loading={busy === 'complete'} onPress={() => Alert.alert('Complete this job?', 'The final amount is calculated from the actual time.', [{ text: 'Back', style: 'cancel' }, { text: 'Complete', onPress: () => action('complete', km ? { actual_km: Number(km.replace(',', '.')) } : {}) }])} />
              </Card>
            ) : null}
            {can.no_show ? (
              <><Gap h={space.md} /><Button variant="ghost" title="Client did not show" onPress={() => Alert.alert('Record a no-show?', `Only after waiting ${j.no_show_wait_minutes ?? 15} minutes past the booked time and trying the client through the app or the control room.`, [{ text: 'Back', style: 'cancel' }, { text: 'Record no-show', style: 'destructive', onPress: () => action('no_show') }])} /></>
            ) : null}
          </Section>
        ) : null}

        {j.live ? (
          <Section title="Contact">
            <Row>
              {can?.chat ? <Button small variant="line" icon="message-square" title="Message" onPress={() => navigation.navigate('Chat', { jobId: id })} style={{ flex: 1 }} /> : null}
              {can?.call ? <Button small variant="line" icon="phone" title="Call client" loading={busy === 'call'} onPress={async () => { setBusy('call'); try { const r = await api.post(`crew/jobs/${id}/call`); Alert.alert('Calling', r.message); } catch (e) { Alert.alert('Not available', errorMessage(e)); } finally { setBusy(null); } }} style={{ flex: 1 }} /> : null}
            </Row>
            <Gap h={space.sm} />
            {control ? <Button variant="ghost" icon="headphones" title="Call the control room" onPress={() => Linking.openURL(`tel:${control}`)} /> : null}
            <Pressable onPress={() => navigation.navigate('Sos', {})} style={styles.sos} accessibilityRole="button" accessibilityLabel="SOS">
              <Icon name="alert-octagon" color="#f3c3b8" /><Label color="#f3c3b8">SOS</Label>
            </Pressable>
          </Section>
        ) : null}

        {j.payout_amount ? <Section title="Your pay for this job"><Money>{rands(j.payout_amount, true)}</Money></Section> : null}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  sos: { marginTop: space.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: space.sm, minHeight: 52, borderRadius: radius.md, borderWidth: 1, borderColor: 'rgba(229,130,111,0.6)', backgroundColor: colors.dangerWash },
});
