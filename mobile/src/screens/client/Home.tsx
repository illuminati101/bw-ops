import React, { useEffect, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { api } from '../../api/client';
import type { Booking } from '../../api/types';
import { useLoader } from '../../lib/hooks';
import { fmt, greeting } from '../../lib/format';
import { currentPosition } from '../../lib/location';
import { MapView, MapPoint } from '../../map/MapView';
import { useDraft } from '../../state/draft';
import { useFeature, useSession } from '../../state/session';
import { colors, fonts, radius, space } from '../../theme';
import { Body, Button, Card, Gap, Icon, Label, Muted, Row, Small, StatusPill, Title } from '../../components/ui';

export default function Home({ navigation }: any) {
  const insets = useSafeAreaInsets();
  const { user } = useSession();
  const onDemand = useFeature('on_demand');
  const { reset } = useDraft();
  const [me, setMe] = useState<{ lat: number; lng: number } | null>(null);

  const upcoming = useLoader(async () => (await api.get<{ data: Booking[] }>('bookings', { scope: 'upcoming' })).data, [], 20000);

  useEffect(() => {
    currentPosition().then(setMe);
  }, []);

  const next = upcoming.data?.[0];
  const points: MapPoint[] = me ? [{ ...me, kind: 'me' }] : [];

  function start(mode: 'on_demand' | 'advance', pickupFirst = false) {
    reset({ mode });
    if (pickupFirst) navigation.navigate('PlaceSearch', { field: 'pickup', then: 'BookService' });
    else navigation.navigate('BookService');
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.night }}>
      <MapView data={{ points }} style={StyleSheet.absoluteFill} center={me ?? undefined} />
      <View style={[styles.top, { paddingTop: insets.top + space.md }]} pointerEvents="box-none">
        <Title style={{ fontSize: 30 }}>{greeting(user?.first_name)}</Title>
      </View>

      <View style={[styles.sheet, { paddingBottom: insets.bottom + space.lg }]}>
        {user?.verification?.required && user.verification.status !== 'verified' && user.verification.status !== 'pending' ? (
          <Pressable onPress={() => navigation.navigate('Verify')} style={styles.notice} accessibilityRole="button">
            <Icon name="shield" color={colors.gold} />
            <View style={{ flex: 1 }}><Label>Verify your ID to book</Label><Small>Two minutes: a photo of your ID and a selfie.</Small></View>
            <Icon name="chevron-right" color={colors.ash} />
          </Pressable>
        ) : !user?.has_payment_method ? (
          <Pressable onPress={() => navigation.navigate('Payment')} style={styles.notice} accessibilityRole="button">
            <Icon name="credit-card" color={colors.gold} />
            <View style={{ flex: 1 }}><Label>Add a card</Label><Small>Needed to book. Nothing is charged until a job is complete.</Small></View>
            <Icon name="chevron-right" color={colors.ash} />
          </Pressable>
        ) : null}

        {next ? (
          <Card onPress={() => navigation.navigate('BookingDetail', { reference: next.reference })} style={{ marginBottom: space.md }}>
            <Row style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
              <View style={{ flex: 1 }}>
                <Small>{next.mode === 'on_demand' ? 'Now' : `${fmt.relativeDay(next.starts_at)}, ${fmt.time(next.starts_at)}`}</Small>
                <Label style={{ marginTop: 2 }}>{next.service.name}</Label>
                <Muted numberOfLines={1}>{next.pickup.address}</Muted>
              </View>
              {next.arrival_pin ? (
                <View style={{ alignItems: 'flex-end' }}>
                  <Small>PIN</Small>
                  <Body style={{ fontFamily: fonts.display, fontSize: 28, color: colors.goldLight, letterSpacing: 3 }}>{next.arrival_pin}</Body>
                </View>
              ) : <StatusPill label={next.status_label} tone="gold" />}
            </Row>
          </Card>
        ) : null}

        <Pressable onPress={() => start(onDemand ? 'on_demand' : 'advance', true)} style={styles.search} accessibilityRole="button" accessibilityLabel="Where should we meet you?">
          <Icon name="map-pin" color={colors.gold} />
          <Body color={colors.mist}>Where should we meet you?</Body>
        </Pressable>
        <Gap h={space.md} />
        <Row>
          {onDemand ? <Button title="Hail now" icon="navigation" onPress={() => start('on_demand')} style={{ flex: 1 }} /> : null}
          <Button title="Book ahead" icon="calendar" variant={onDemand ? 'line' : 'gold'} onPress={() => start('advance')} style={{ flex: 1 }} />
        </Row>
        {!onDemand ? <Muted style={{ marginTop: space.sm }}>Book at least a few hours ahead. Our control room confirms every booking.</Muted> : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  top: { position: 'absolute', left: 0, right: 0, paddingHorizontal: space.lg, paddingBottom: space.xl, backgroundColor: 'rgba(7,7,7,0.72)' },
  sheet: { position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: colors.night, borderTopLeftRadius: 24, borderTopRightRadius: 24, paddingHorizontal: space.lg, paddingTop: space.xl, borderTopWidth: StyleSheet.hairlineWidth, borderColor: colors.lineStrong },
  search: { flexDirection: 'row', alignItems: 'center', gap: space.md, minHeight: 56, borderRadius: radius.md, backgroundColor: colors.graphite, paddingHorizontal: space.lg },
  notice: { flexDirection: 'row', alignItems: 'center', gap: space.md, padding: space.md, borderRadius: radius.md, borderWidth: 1, borderColor: 'rgba(201,168,106,0.4)', backgroundColor: colors.goldWash, marginBottom: space.md },
});
