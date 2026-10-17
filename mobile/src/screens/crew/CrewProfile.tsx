import React, { useState } from 'react';
import { Alert, View } from 'react-native';
import { api, errorMessage } from '../../api/client';
import type { CrewMe } from '../../api/types';
import { APP_VERSION } from '../../config';
import { stopTracking } from '../../lib/crewLocation';
import { useLoader } from '../../lib/hooks';
import { fmt } from '../../lib/format';
import { useSession } from '../../state/session';
import { colors, space } from '../../theme';
import { Button, Card, Divider, Field, Gap, Label, ListItem, Muted, Row, Screen, Section, Small, StatusPill, Title } from '../../components/ui';

type Shift = { id: number; starts_at: string; ends_at: string; status: string; notes: string | null };

export default function CrewProfile({ navigation }: any) {
  const { signOut } = useSession();
  const me = useLoader(async () => api.get<CrewMe>('crew/me'));
  const roster = useLoader(async () => (await api.get<{ shifts: Shift[] }>('crew/roster')).shifts);
  const [safetyPin, setSafetyPin] = useState('');
  const [duressPin, setDuressPin] = useState('');
  const [saving, setSaving] = useState(false);
  const op = me.data?.operator;

  async function savePins() {
    setSaving(true);
    try {
      await api.post('me/safety-pins', { safety_pin: safetyPin, duress_pin: duressPin || null });
      setSafetyPin('');
      setDuressPin('');
      me.reload();
      Alert.alert('Saved', 'Your safety PINs are set.');
    } catch (e) {
      Alert.alert('Not saved', errorMessage(e));
    } finally {
      setSaving(false);
    }
  }

  async function logout() {
    try { await api.post('crew/status', { online: false }); } catch {}
    await stopTracking();
    await signOut();
  }

  return (
    <Screen onRefresh={() => { me.reload(); roster.reload(); }} refreshing={me.refreshing}>
      <Title>{op?.name ?? 'Profile'}</Title>
      <Muted>{op?.call_sign}{op?.psira_number ? `, PSIRA ${op.psira_number}${op.psira_grade ? ` (Grade ${op.psira_grade})` : ''}` : ''}</Muted>

      {op?.features?.length ? (
        <Section title="Your capabilities">
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: space.sm }}>
            {op.features.map((f) => <StatusPill key={f.name} label={f.name} tone={f.active ? 'gold' : 'alert'} />)}
          </View>
          {op.features.some((f) => !f.active) ? <Small style={{ marginTop: space.sm }}>Red items are paused until the related document is valid.</Small> : null}
        </Section>
      ) : null}

      <Section title="Roster">
        {roster.data?.length ? (
          <Card style={{ padding: 0 }}>
            {roster.data.map((s, i) => (
              <View key={s.id}>
                {i ? <Divider /> : null}
                <Row style={{ padding: space.lg, justifyContent: 'space-between' }}>
                  <View><Label>{fmt.relativeDay(s.starts_at)}</Label><Small>{fmt.time(s.starts_at)} to {fmt.time(s.ends_at)}{s.notes ? `, ${s.notes}` : ''}</Small></View>
                  <StatusPill label={s.status === 'requested' ? 'Requested' : s.status === 'confirmed' ? 'Confirmed' : s.status} tone={s.status === 'confirmed' ? 'ok' : 'plain'} />
                </Row>
              </View>
            ))}
          </Card>
        ) : <Muted>No shifts on your roster.</Muted>}
      </Section>

      <Section title="Safety PIN">
        <Muted>Cancels an SOS raised by mistake. The duress PIN looks like a normal cancel but tells the control room you are in danger.</Muted>
        <Small color={colors.mist} style={{ marginTop: space.sm }}>{op?.safety_pin_set ? 'Safety PIN is set.' : 'No safety PIN yet.'}</Small>
        <Gap h={space.md} />
        <Row>
          <Field label="Safety PIN" value={safetyPin} onChangeText={(t) => setSafetyPin(t.replace(/\D/g, '').slice(0, 4))} keyboardType="number-pad" secureTextEntry style={{ flex: 1 }} />
          <Field label="Duress PIN" value={duressPin} onChangeText={(t) => setDuressPin(t.replace(/\D/g, '').slice(0, 4))} keyboardType="number-pad" secureTextEntry style={{ flex: 1 }} />
        </Row>
        <Button variant="line" title="Save PINs" onPress={savePins} loading={saving} disabled={safetyPin.length !== 4} />
      </Section>

      <Section>
        <Card style={{ padding: 0 }}>
          <ListItem icon="server" title="Server" onPress={() => navigation.navigate('ServerSettings')} />
          <Divider />
          <ListItem icon="log-out" title="Sign out" onPress={logout} right={<View />} />
        </Card>
      </Section>
      <Gap />
      <Small center>BW Ops Crew {APP_VERSION}</Small>
    </Screen>
  );
}
