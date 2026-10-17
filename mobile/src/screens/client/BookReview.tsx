import React, { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import * as WebBrowser from 'expo-web-browser';
import { api, errorMessage } from '../../api/client';
import type { Quote } from '../../api/types';
import { fmt, rands } from '../../lib/format';
import { toPayload, useDraft } from '../../state/draft';
import { useFeature, useSession } from '../../state/session';
import { colors, space } from '../../theme';
import { Banner, Body, Button, Card, Divider, Gap, Label, Money, Muted, Radio, Row, Screen, Section, Small, Title } from '../../components/ui';

type SavedCard = { id: number; label: string; is_default: boolean; expired: boolean };

export default function BookReview({ navigation, route }: any) {
  const quote: Quote = route.params.quote;
  const { draft, set, reset } = useDraft();
  const { user, config, refreshUser } = useSession();
  const cardMachine = useFeature('card_machine');
  const eftFeature = useFeature('eft_invoicing');
  const [cards, setCards] = useState<SavedCard[] | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const s = draft.service!;

  useFocusEffect(useCallback(() => {
    refreshUser();
    api.get<{ methods: SavedCard[] }>('payment-methods').then((r) => {
      setCards(r.methods);
      if (!draft.paymentMethodId && r.methods.length) set({ paymentMethodId: (r.methods.find((m) => m.is_default) ?? r.methods[0]).id });
    }).catch(() => setCards([]));
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []));

  const eftAllowed = eftFeature && (user?.eft_approved || user?.companies.some((c) => c.eft));
  const needsVerify = user?.verification?.required && ['unverified', 'rejected'].includes(user.verification.status);
  const needsCard = draft.paymentMethod !== 'eft' && cards !== null && cards.length === 0;
  const freeHours = Math.max(...(config?.cancellation.tiers ?? [{ hours: 24, percent: 0 }]).map((t) => Number(t.hours)));

  async function confirm() {
    setError(null);
    setBusy(true);
    try {
      const r = await api.post('bookings', toPayload(draft));
      const ref = r.data?.reference ?? r.reference;
      reset();
      navigation.reset({ index: 1, routes: [{ name: 'Tabs' }, { name: 'BookingDetail', params: { reference: ref, fresh: true } }] });
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  const when = draft.mode === 'on_demand'
    ? 'As soon as possible'
    : s.structure === 'multi_day'
      ? `${draft.schedule.start_date} to ${draft.schedule.end_date}, ${draft.schedule.start_time} to ${draft.schedule.end_time}`
      : `${fmt.relativeDay(fmt.isoSA(draft.start))}, ${String(draft.start.getHours()).padStart(2, '0')}:${String(draft.start.getMinutes()).padStart(2, '0')} for ${fmt.minutes(draft.duration)}`;

  return (
    <Screen edges={['bottom']}>
      <Title>Review and book</Title>
      <Gap h={space.lg} />
      <Card>
        <Label>{s.name}</Label>
        <Muted style={{ marginTop: 4 }}>{when}</Muted>
        <Gap h={space.md} />
        <Small>{s.includes_transport ? 'Pickup' : 'Meeting point'}</Small>
        <Body>{draft.pickup?.address}</Body>
        {draft.stops.map((st, i) => <View key={i} style={{ marginTop: space.sm }}><Small>Stop {i + 1}</Small><Body>{st.address}</Body></View>)}
        {draft.dropoff ? <View style={{ marginTop: space.sm }}><Small>Drop-off</Small><Body>{draft.dropoff.address}</Body></View> : null}
        {draft.forSomeoneElse ? <View style={{ marginTop: space.sm }}><Small>For</Small><Body>{draft.principalName}</Body></View> : null}
      </Card>

      <Section title="Price">
        <Card>
          {quote.custom_quote ? (
            <>
              <Money>Tailored quote</Money>
              <Muted style={{ marginTop: space.sm }}>Our team will review your request and send a firm price to accept, usually within a few hours. Estimate: about {rands(quote.total)}.</Muted>
            </>
          ) : (
            <>
              {quote.lines.map((l, i) => (
                <Row key={i} style={{ justifyContent: 'space-between', marginBottom: 6 }}><Muted style={{ flex: 1 }}>{l.label}</Muted><Body>{rands(l.amount, true)}</Body></Row>
              ))}
              {quote.surcharge ? <Row style={{ justifyContent: 'space-between', marginBottom: 6 }}><Muted style={{ flex: 1 }}>{quote.surcharge.label}</Muted><Body>{rands(quote.surcharge.amount, true)}</Body></Row> : null}
              <Divider style={{ marginVertical: space.md }} />
              <Row style={{ justifyContent: 'space-between' }}><Label>Total</Label><Money>{rands(quote.total)}</Money></Row>
              {quote.vat_included > 0 ? <Small>Includes VAT of {rands(quote.vat_included, true)}</Small> : null}
              <Small style={{ marginTop: space.sm }}>Extra time is {rands(quote.overtime_rate)} per hour after a short grace period.</Small>
            </>
          )}
        </Card>
      </Section>

      <Section title="Payment after the job">
        {cards === null ? <Muted>Loading your cards…</Muted> : null}
        {cards?.map((c) => (
          <Radio key={c.id} label={c.label} sub={c.expired ? 'Expired' : c.is_default ? 'Default card' : undefined} on={draft.paymentMethod === 'card' && draft.paymentMethodId === c.id} onPress={() => set({ paymentMethod: 'card', paymentMethodId: c.id })} />
        ))}
        {cardMachine && cards && cards.length > 0 ? <Radio label="Card machine at the end" sub="Your saved card still secures the booking." on={draft.paymentMethod === 'card_machine'} onPress={() => set({ paymentMethod: 'card_machine' })} /> : null}
        {eftAllowed ? <Radio label="EFT on monthly invoice" on={draft.paymentMethod === 'eft'} onPress={() => set({ paymentMethod: 'eft' })} /> : null}
        {user?.companies.length ? (
          <>
            <Gap h={space.sm} />
            <Label style={{ marginBottom: space.sm }}>Bill to</Label>
            <Radio label="Me personally" on={!draft.companyId} onPress={() => set({ companyId: null })} />
            {user.companies.map((c) => <Radio key={c.id} label={c.name} on={draft.companyId === c.id} onPress={() => set({ companyId: c.id })} />)}
          </>
        ) : null}
        {needsCard ? <Button variant="line" icon="credit-card" title="Add a card" onPress={() => navigation.navigate('Payment')} /> : null}
      </Section>

      <Gap h={space.xl} />
      <Small>
        Free cancellation up to {freeHours} hours before. No-show fee {rands(quote.no_show_fee)}.
        {quote.hold_amount > 0 ? ` We will hold ${rands(quote.hold_amount)} on your card until the job ends.` : ''}{' '}
        <Small color={colors.mist} style={{ textDecorationLine: 'underline' }} onPress={() => config && WebBrowser.openBrowserAsync(config.legal.cancellation_url)}>Cancellation policy</Small>
      </Small>
      <Gap h={space.lg} />
      {error ? <><Banner tone="danger" text={error} /><Gap h={space.md} /></> : null}
      {needsVerify ? (
        <Button title="Verify your ID to book" icon="shield" onPress={() => navigation.navigate('Verify')} />
      ) : (
        <Button
          haptic
          title={quote.custom_quote ? 'Request quote' : draft.mode === 'on_demand' ? 'Hail now' : 'Confirm booking'}
          onPress={confirm}
          loading={busy}
          disabled={needsCard}
        />
      )}
    </Screen>
  );
}
