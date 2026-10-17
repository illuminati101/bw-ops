import React from 'react';
import { View } from 'react-native';
import { api } from '../../api/client';
import type { CrewMe } from '../../api/types';
import { useLoader } from '../../lib/hooks';
import { fmt, rands } from '../../lib/format';
import { colors, space } from '../../theme';
import { Card, Divider, Empty, Gap, Label, Money, Muted, Row, Screen, Section, Small, StatusPill, Title } from '../../components/ui';

type Item = { id: number; reference: string; service: string; role: string; finished_at: string | null; payout_amount: number | null; payout_status: string | null };

export default function History() {
  const me = useLoader(async () => api.get<CrewMe>('crew/me'));
  const list = useLoader(async () => (await api.get<{ data: Item[] }>('crew/history')).data);
  const e = me.data?.earnings;

  return (
    <Screen onRefresh={() => { me.reload(); list.reload(); }} refreshing={list.refreshing}>
      <Title>{e ? 'Earnings' : 'History'}</Title>
      {e ? (
        <>
          <Gap />
          <Row>
            <Card style={{ flex: 1 }}><Small>This week</Small><Money style={{ fontSize: 26 }}>{rands(e.this_week)}</Money></Card>
            <Card style={{ flex: 1 }}><Small>This month</Small><Money style={{ fontSize: 26 }}>{rands(e.this_month)}</Money></Card>
          </Row>
          <Gap h={space.md} />
          <Row>
            <Card style={{ flex: 1 }}><Small>Awaiting payout</Small><Money style={{ fontSize: 26 }}>{rands(e.unpaid)}</Money></Card>
            <Card style={{ flex: 1 }}><Small>Jobs this month</Small><Money style={{ fontSize: 26, color: colors.bone }}>{e.jobs_this_month}</Money></Card>
          </Row>
        </>
      ) : null}
      <Section title="Completed jobs">
        {list.data?.length ? (
          <Card style={{ padding: 0 }}>
            {list.data.map((j, i) => (
              <View key={j.id}>
                {i ? <Divider /> : null}
                <Row style={{ padding: space.lg, justifyContent: 'space-between', alignItems: 'flex-start' }}>
                  <View style={{ flex: 1 }}>
                    <Label>{j.service}</Label>
                    <Small>{j.reference}, {j.role}{j.finished_at ? `, ${fmt.dateTime(j.finished_at)}` : ''}</Small>
                  </View>
                  {j.payout_amount !== null ? (
                    <View style={{ alignItems: 'flex-end', gap: 4 }}>
                      <Label>{rands(j.payout_amount, true)}</Label>
                      {j.payout_status ? <StatusPill label={j.payout_status === 'paid' ? 'Paid' : 'Unpaid'} tone={j.payout_status === 'paid' ? 'ok' : 'plain'} /> : null}
                    </View>
                  ) : null}
                </Row>
              </View>
            ))}
          </Card>
        ) : list.data ? <Empty title="No completed jobs yet" text="Finished jobs appear here." /> : <Muted>Loading…</Muted>}
      </Section>
    </Screen>
  );
}
