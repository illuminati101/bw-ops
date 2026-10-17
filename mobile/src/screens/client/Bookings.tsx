import React, { useState } from 'react';
import { View } from 'react-native';
import { api } from '../../api/client';
import type { Booking } from '../../api/types';
import { useLoader } from '../../lib/hooks';
import { fmt, rands } from '../../lib/format';
import { colors, space } from '../../theme';
import { Banner, Button, Card, Empty, Gap, Label, Muted, Row, Screen, Segmented, Small, StatusPill, Title } from '../../components/ui';

export function statusTone(status: string): 'gold' | 'ok' | 'alert' | 'plain' {
  if (['assigned', 'en_route', 'arrived', 'in_progress', 'confirmed', 'searching'].includes(status)) return 'gold';
  if (status === 'completed') return 'ok';
  if (status === 'no_show') return 'alert';
  return 'plain';
}

export default function Bookings({ navigation }: any) {
  const [scope, setScope] = useState<'upcoming' | 'past'>('upcoming');
  const { data, error, refreshing, reload, loading } = useLoader(async () => (await api.get<{ data: Booking[] }>('bookings', { scope })).data, [scope], 30000);

  return (
    <Screen onRefresh={reload} refreshing={refreshing}>
      <Title>Bookings</Title>
      <Gap h={space.lg} />
      <Segmented value={scope} onChange={setScope} options={[{ value: 'upcoming', label: 'Upcoming' }, { value: 'past', label: 'Past' }]} />
      <Gap h={space.lg} />
      {error ? <Banner tone="danger" text={error} /> : null}
      {!loading && data && data.length === 0 ? (
        <Empty
          title={scope === 'past' ? 'No past bookings yet' : 'Nothing booked yet'}
          text="Book ahead, or hail a protected ride in minutes."
          action={scope === 'upcoming' ? <Button title="Make a booking" onPress={() => navigation.navigate('Home')} /> : undefined}
        />
      ) : null}
      {data?.map((b) => (
        <Card key={b.id} onPress={() => navigation.navigate('BookingDetail', { reference: b.reference })} style={{ marginBottom: space.md }}>
          <Row style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
            <View style={{ flex: 1 }}>
              <Small>{b.mode === 'on_demand' ? `On demand, ${fmt.dayShort(b.starts_at)}` : fmt.dateTime(b.starts_at)}</Small>
              <Label style={{ marginTop: 2 }}>{b.service.name}{b.principal ? <Muted> for {b.principal.name}</Muted> : null}</Label>
              <Muted numberOfLines={1}>{b.pickup.address}</Muted>
            </View>
            <View style={{ alignItems: 'flex-end', gap: 6 }}>
              <StatusPill label={b.status_label} tone={statusTone(b.status)} />
              <Small color={colors.mist}>{b.quote.awaiting_quote ? 'Quote pending' : rands(b.quote.final_total ?? b.quote.total)}</Small>
            </View>
          </Row>
          {b.reconfirm.required ? <Small color={colors.danger} style={{ marginTop: space.sm }}>Please reconfirm this booking</Small> : null}
        </Card>
      ))}
    </Screen>
  );
}
