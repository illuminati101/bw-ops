import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, Image, Modal, StyleSheet, Switch, Vibration, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { activateKeepAwakeAsync, deactivateKeepAwake } from 'expo-keep-awake';
import * as Haptics from 'expo-haptics';
import { api, errorMessage } from '../../api/client';
import type { CrewMe, Job, Offer } from '../../api/types';
import { useInterval, useLoader } from '../../lib/hooks';
import { fmt, rands } from '../../lib/format';
import { startTracking, stopTracking } from '../../lib/crewLocation';
import { currentPosition } from '../../lib/location';
import { colors, fonts, space } from '../../theme';
import { Banner, Body, Button, Card, Gap, Label, Money, Muted, Row, Screen, Section, Small, StatusPill, Title } from '../../components/ui';

type Feed = { is_online: boolean; offers: Offer[]; current: Job | null; awaiting_response: Job[]; upcoming: Job[] };

export default function CrewHome({ navigation }: any) {
  const me = useLoader(async () => api.get<CrewMe>('crew/me'), []);
  const [online, setOnline] = useState(false);
  const [toggling, setToggling] = useState(false);
  const [tracking, setTracking] = useState<'background' | 'foreground' | 'denied' | null>(null);
  const feed = useLoader(async () => {
    const f = await api.get<Feed>('crew/feed');
    setOnline(f.is_online);
    return f;
  }, [], online ? 4000 : 15000);

  // Keep location sharing in step with the server's online state (e.g. after an app restart).
  useEffect(() => {
    if (online && tracking === null) startTracking().then(setTracking);
    if (!online && tracking) { stopTracking(); setTracking(null); }
  }, [online, tracking]);

  async function toggle(next: boolean) {
    setToggling(true);
    try {
      let pos = null;
      if (next) {
        const mode = await startTracking();
        setTracking(mode);
        if (mode === 'denied') {
          Alert.alert('Location needed', 'Allow location access in Settings to go online. The control room needs it to offer you jobs and keep clients informed.');
          setToggling(false);
          return;
        }
        pos = await currentPosition(5000);
        activateKeepAwakeAsync('crew-online').catch(() => {});
      } else {
        await stopTracking();
        setTracking(null);
        deactivateKeepAwake('crew-online').catch(() => {});
      }
      const r = await api.post<{ is_online: boolean }>('crew/status', { online: next, lat: pos?.lat, lng: pos?.lng });
      setOnline(r.is_online);
      feed.silentReload();
    } catch (e) {
      Alert.alert('Status not changed', errorMessage(e));
    } finally {
      setToggling(false);
    }
  }

  async function respond(job: Job, accept: boolean) {
    try {
      await api.post(`crew/assignments/${job.id}/respond`, { accept });
      feed.silentReload();
    } catch (e) {
      Alert.alert('Not done', errorMessage(e));
    }
  }

  const op = me.data?.operator;
  const f = feed.data;
  const offer = f?.offers?.[0];

  return (
    <Screen onRefresh={() => { me.reload(); feed.reload(); }} refreshing={feed.refreshing}>
      <Row style={{ justifyContent: 'space-between' }}>
        <Row>
          {op?.photo_url ? <Image source={{ uri: op.photo_url }} style={styles.avatar} /> : <View style={[styles.avatar, styles.avatarEmpty]}><Title style={{ fontSize: 22, color: colors.goldLight }}>{op?.first_name?.[0] ?? ''}</Title></View>}
          <View>
            <Title style={{ fontSize: 26 }}>{op?.first_name ?? 'Crew'}</Title>
            <Small>{op?.call_sign}{op?.vehicle ? `, ${op.vehicle.label}` : ''}</Small>
          </View>
        </Row>
      </Row>

      <Card style={{ marginTop: space.xl }} tone={online ? 'gold' : undefined}>
        <Row style={{ justifyContent: 'space-between' }}>
          <View style={{ flex: 1 }}>
            <Label>{online ? 'You are online' : 'You are offline'}</Label>
            <Muted>{online ? (tracking === 'foreground' ? 'Keep the app open so the control room can see you.' : 'Receiving job offers near you.') : 'Go online to receive on-demand jobs.'}</Muted>
          </View>
          <Switch value={online} onValueChange={toggle} disabled={toggling || (op ? !op.can_work : false)} trackColor={{ true: colors.gold, false: colors.smoke }} thumbColor={colors.bone} {...({ activeThumbColor: colors.bone } as any)} accessibilityLabel="Online" />
        </Row>
      </Card>

      {op && !op.can_work ? (
        <><Gap h={space.md} /><Banner tone="danger" title="You can't take jobs right now" text={op.issues.join('\n') || 'Contact the operations team.'} action={<Button small variant="line" title="View documents" onPress={() => navigation.navigate('Documents')} />} /></>
      ) : op?.issues?.length ? (
        <><Gap h={space.md} /><Banner text={op.issues.join('\n')} /></>
      ) : null}

      {f?.current ? (
        <Section title="Current job">
          <JobCard job={f.current} onPress={() => navigation.navigate('Job', { id: f.current!.id })} highlight />
        </Section>
      ) : null}

      {f?.awaiting_response?.length ? (
        <Section title="Assigned to you">
          {f.awaiting_response.map((j) => (
            <Card key={j.id} style={{ marginBottom: space.md }} tone="gold">
              <JobSummary job={j} />
              <Row style={{ marginTop: space.md }}>
                <Button small title="Accept" onPress={() => respond(j, true)} style={{ flex: 1 }} />
                <Button small variant="line" title="Decline" onPress={() => Alert.alert('Decline this job?', 'The control room will reassign it.', [{ text: 'Back', style: 'cancel' }, { text: 'Decline', style: 'destructive', onPress: () => respond(j, false) }])} style={{ flex: 1 }} />
              </Row>
            </Card>
          ))}
        </Section>
      ) : null}

      <Section title="Upcoming">
        {f?.upcoming?.length ? f.upcoming.map((j) => <JobCard key={j.id} job={j} onPress={() => navigation.navigate('Job', { id: j.id })} />) : <Muted>No upcoming jobs.</Muted>}
      </Section>

      {me.data?.earnings ? (
        <Section title="Earnings">
          <Row>
            <Card style={{ flex: 1 }}><Small>This week</Small><Money style={{ fontSize: 26 }}>{rands(me.data.earnings.this_week)}</Money></Card>
            <Card style={{ flex: 1 }}><Small>This month</Small><Money style={{ fontSize: 26 }}>{rands(me.data.earnings.this_month)}</Money></Card>
          </Row>
        </Section>
      ) : null}

      <OfferModal offer={offer} onDone={(job) => { feed.silentReload(); if (job) navigation.navigate('Job', { id: job.id }); }} />
    </Screen>
  );
}

function JobSummary({ job }: { job: Job }) {
  return (
    <View>
      <Row style={{ justifyContent: 'space-between' }}>
        <Small>{job.mode === 'on_demand' ? 'On demand' : `${fmt.relativeDay(job.starts_at)}, ${fmt.time(job.starts_at)} to ${fmt.time(job.ends_at)}`}</Small>
        <StatusPill label={job.status_label} tone="gold" />
      </Row>
      <Label style={{ marginTop: 4 }}>{job.service} <Muted>{job.role_label}</Muted></Label>
      {job.pickup ? <Muted numberOfLines={1}>{job.pickup.address}</Muted> : null}
      {job.payout_amount ? <Small color={colors.goldLight}>{rands(job.payout_amount)}</Small> : null}
    </View>
  );
}

function JobCard({ job, onPress, highlight }: { job: Job; onPress: () => void; highlight?: boolean }) {
  return <Card onPress={onPress} tone={highlight ? 'gold' : undefined} style={{ marginBottom: space.md }}><JobSummary job={job} /></Card>;
}

function OfferModal({ offer, onDone }: { offer?: Offer; onDone: (job?: Job) => void }) {
  const [left, setLeft] = useState(0);
  const [busy, setBusy] = useState(false);
  const shown = useRef<number | null>(null);

  useEffect(() => {
    if (offer && shown.current !== offer.id) {
      shown.current = offer.id;
      setLeft(Math.round(offer.seconds_left));
      Vibration.vibrate([0, 400, 200, 400, 200, 400]);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning).catch(() => {});
    }
  }, [offer]);
  useInterval(() => setLeft((l) => Math.max(0, l - 1)), offer ? 1000 : null);

  if (!offer) return null;

  async function answer(accept: boolean) {
    setBusy(true);
    try {
      if (accept) {
        const r = await api.post<{ job: Job }>(`crew/offers/${offer!.id}/accept`);
        onDone(r.job);
      } else {
        await api.post(`crew/offers/${offer!.id}/decline`);
        onDone();
      }
    } catch (e) {
      Alert.alert('Offer', errorMessage(e));
      onDone();
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal visible={left > 0} transparent animationType="slide" onRequestClose={() => answer(false)}>
      <View style={styles.backdrop}>
        <View style={styles.offer}>
          <Row style={{ justifyContent: 'space-between' }}>
            <Label color={colors.goldLight}>New job offer</Label>
            <Body style={{ fontFamily: fonts.bold, fontSize: 22, color: left <= 10 ? colors.danger : colors.bone }}>{left}s</Body>
          </Row>
          <Title style={{ marginTop: space.md }}>{offer.service}</Title>
          <Muted>{offer.role === 'driver' ? 'Chauffeur' : offer.role === 'guard' ? 'Protection officer' : offer.role}{offer.distance_km != null ? `, ${offer.distance_km.toFixed(1)} km away` : ''}</Muted>
          <Gap h={space.md} />
          <Small>Pickup</Small>
          <Body>{offer.pickup}</Body>
          {offer.dropoff ? <><Gap h={space.sm} /><Small>Drop-off</Small><Body>{offer.dropoff}</Body></> : null}
          <Gap h={space.sm} />
          <Small>About {fmt.minutes(offer.duration_minutes)}{offer.requirements.length ? `. ${offer.requirements.join(', ')}` : ''}</Small>
          {offer.payout_estimate ? <Money style={{ marginTop: space.sm, fontSize: 28 }}>{rands(offer.payout_estimate)}</Money> : null}
          <Gap h={space.xl} />
          <Button haptic title="Accept job" onPress={() => answer(true)} loading={busy} />
          <Gap h={space.sm} />
          <Button variant="ghost" title="Decline" onPress={() => answer(false)} disabled={busy} />
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  avatar: { width: 48, height: 48, borderRadius: 24 },
  avatarEmpty: { backgroundColor: colors.graphite, alignItems: 'center', justifyContent: 'center' },
  backdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.7)', justifyContent: 'flex-end' },
  offer: { backgroundColor: colors.carbon, borderTopLeftRadius: 24, borderTopRightRadius: 24, padding: space.xl, paddingBottom: 40, borderTopWidth: 1, borderColor: 'rgba(201,168,106,0.5)' },
});
