import React, { useState } from 'react';
import { Alert, View } from 'react-native';
import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';
import { api, errorMessage } from '../../api/client';
import { useLoader } from '../../lib/hooks';
import { useSession } from '../../state/session';
import { colors, space } from '../../theme';
import { Banner, Button, Card, Divider, Gap, Label, ListItem, Muted, Screen, Small, Title } from '../../components/ui';

type Method = { id: number; label: string; is_default: boolean; expired: boolean; gateway: string };

export default function Payment({ navigation }: any) {
  const { user, refreshUser } = useSession();
  const [busy, setBusy] = useState(false);
  const [note, setNote] = useState<string | null>(null);
  const { data, reload, refreshing, error } = useLoader(async () => api.get<{ methods: Method[]; record_only: boolean }>('payment-methods'));

  async function addCard() {
    setBusy(true);
    setNote(null);
    try {
      const returnUrl = Linking.createURL('payment-complete');
      const r = await api.post<{ redirect_url: string | null; record_only: boolean }>('payment-methods/setup', { return_url: returnUrl });
      if (r.redirect_url) {
        const result = await WebBrowser.openAuthSessionAsync(r.redirect_url, returnUrl);
        if (result.type === 'success') {
          const { queryParams } = Linking.parse(result.url);
          setNote(String(queryParams?.message ?? (queryParams?.status === 'success' ? 'Card saved.' : 'Card not saved.')));
        }
      } else {
        setNote('Card on file saved. Our team will settle each booking with you until online card payments go live.');
      }
      await reload();
      await refreshUser();
    } catch (e) {
      Alert.alert('Card not added', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  function manage(m: Method) {
    Alert.alert(m.label, undefined, [
      ...(m.is_default ? [] : [{ text: 'Make default', onPress: async () => { await api.post(`payment-methods/${m.id}/default`).catch((e) => Alert.alert('Not done', errorMessage(e))); reload(); } }]),
      { text: 'Remove card', style: 'destructive' as const, onPress: async () => { try { await api.delete(`payment-methods/${m.id}`); reload(); refreshUser(); } catch (e) { Alert.alert('Not removed', errorMessage(e)); } } },
      { text: 'Close', style: 'cancel' as const },
    ]);
  }

  return (
    <Screen edges={['bottom']} onRefresh={reload} refreshing={refreshing}>
      <Title>Payment</Title>
      <Muted style={{ marginTop: space.sm }}>You are only charged after a job is complete. A saved card secures your bookings.</Muted>
      <Gap h={space.xl} />
      {note ? <><Banner text={note} /><Gap h={space.md} /></> : null}
      {error ? <><Banner tone="danger" text={error} /><Gap h={space.md} /></> : null}
      {data?.methods.length ? (
        <Card style={{ padding: 0 }}>
          {data.methods.map((m, i) => (
            <View key={m.id}>
              {i ? <Divider /> : null}
              <ListItem icon="credit-card" title={m.label} sub={[m.is_default ? 'Default' : null, m.expired ? 'Expired' : null].filter(Boolean).join(', ') || undefined} onPress={() => manage(m)} />
            </View>
          ))}
        </Card>
      ) : data ? <Muted>No cards saved yet.</Muted> : null}
      <Gap h={space.lg} />
      {!data?.record_only && !user?.email ? (
        <><Banner text="Add an email address to your details first. Our payment provider sends card confirmations to it." action={<Button small variant="line" title="Add email" onPress={() => navigation.navigate('EditProfile')} />} /><Gap h={space.md} /></>
      ) : null}
      <Button title={data?.methods.length ? 'Add another card' : 'Add a card'} icon="plus" onPress={addCard} loading={busy} disabled={!data?.record_only && !user?.email} />
      <Gap h={space.xxl} />
      <Label>How payment works</Label>
      <Gap h={space.sm} />
      {data?.record_only ? (
        <Muted>Online card payments are being connected. Until then we keep a card-on-file record, and our team settles each booking with you after the job by card machine or EFT.</Muted>
      ) : (
        <Muted>To save your card, our payment provider Paystack charges a small amount and refunds it straight away. Your card details are held by Paystack, never by us.</Muted>
      )}
      <Gap h={space.sm} />
      <Muted>When a job ends, the agreed price plus any extra time is charged to your default card and a receipt is emailed to you. On high-value bookings we may place a temporary hold for the no-show fee, released when the job ends.</Muted>
      <Gap h={space.sm} />
      <Small color={colors.ash}>Card machine and EFT options appear at checkout when available on your account.</Small>
    </Screen>
  );
}
