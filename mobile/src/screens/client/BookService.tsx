import React from 'react';
import { View } from 'react-native';
import { api } from '../../api/client';
import type { Service } from '../../api/types';
import { useLoader } from '../../lib/hooks';
import { rands } from '../../lib/format';
import { defaultStart, useDraft } from '../../state/draft';
import { useSession } from '../../state/session';
import { colors, space } from '../../theme';
import { Banner, Card, Gap, H2, Loading, Muted, Row, Screen, Small, Title } from '../../components/ui';

export default function BookService({ navigation }: any) {
  const { draft, set } = useDraft();
  const { config } = useSession();
  const { data, error, loading, reload, refreshing } = useLoader(async () => (await api.get<{ data: Service[] }>('services')).data);

  if (loading && !data) return <Loading />;
  const list = (data ?? []).filter((s) => (draft.mode === 'on_demand' ? s.allows_on_demand : s.allows_advance));

  function pick(s: Service) {
    const notice = s.structure === 'single' ? config?.booking.notice_minutes ?? s.min_notice_minutes : config?.booking.notice_minutes_team ?? s.min_notice_minutes;
    set({
      service: s,
      guards: s.default_guards,
      vehicles: Math.max(1, s.default_vehicles),
      duration: Math.max(draft.duration, s.min_duration_minutes),
      start: defaultStart(Math.max(s.min_notice_minutes, notice)),
      extraIds: [],
      dropoff: s.includes_transport ? draft.dropoff : null,
      stops: s.includes_transport ? draft.stops : [],
    });
    navigation.navigate('BookDetails');
  }

  return (
    <Screen edges={['bottom']} onRefresh={reload} refreshing={refreshing}>
      <Title>{draft.mode === 'on_demand' ? 'What do you need now?' : 'Choose a service'}</Title>
      <Muted style={{ marginTop: space.sm }}>{draft.mode === 'on_demand' ? 'Services we can send in minutes.' : 'Prices update as you choose. Nothing is charged until the job is done.'}</Muted>
      <Gap h={space.xl} />
      {error ? <Banner tone="danger" text={error} /> : null}
      {list.map((s) => (
        <Card key={s.id} onPress={() => pick(s)} style={{ marginBottom: space.md }}>
          <Row style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
            <View style={{ flex: 1 }}>
              <H2>{s.name}</H2>
              {s.summary ? <Muted style={{ marginTop: 4 }}>{s.summary}</Muted> : null}
            </View>
          </Row>
          <Small color={colors.goldLight} style={{ marginTop: space.md }}>{s.custom_quote ? 'Tailored quote' : s.from_price ? `From ${rands(s.from_price)}` : ''}</Small>
        </Card>
      ))}
      {!list.length && !error ? <Muted>No services are available for this right now. Try booking ahead, or call the control room.</Muted> : null}
    </Screen>
  );
}
