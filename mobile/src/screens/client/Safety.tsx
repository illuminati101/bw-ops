import React, { useState } from 'react';
import { Alert, View } from 'react-native';
import { api, ApiError, errorMessage } from '../../api/client';
import { useLoader } from '../../lib/hooks';
import { displayPhone } from '../../lib/format';
import { useSession } from '../../state/session';
import { colors, space } from '../../theme';
import { Button, Card, Check, Divider, Field, Gap, Label, ListItem, Muted, Row, Screen, Section, Small, Title } from '../../components/ui';

type Contact = { id: number; name: string; phone: string; relationship: string | null; notify_on_sos: boolean };

export default function Safety() {
  const { user, refreshUser } = useSession();
  const [safetyPin, setSafetyPin] = useState('');
  const [duressPin, setDuressPin] = useState('');
  const [pinError, setPinError] = useState<string | undefined>();
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [relationship, setRelationship] = useState('');
  const [notify, setNotify] = useState(true);
  const [contactError, setContactError] = useState<string | undefined>();
  const contacts = useLoader(async () => (await api.get<{ contacts: Contact[] }>('me/contacts')).contacts);

  async function savePins() {
    setSaving(true);
    setPinError(undefined);
    setSaved(false);
    try {
      await api.post('me/safety-pins', { safety_pin: safetyPin, duress_pin: duressPin || null });
      setSafetyPin('');
      setDuressPin('');
      setSaved(true);
      refreshUser();
    } catch (e) {
      setPinError(e instanceof ApiError ? e.message : errorMessage(e));
    } finally {
      setSaving(false);
    }
  }

  async function addContact() {
    setContactError(undefined);
    try {
      await api.post('me/contacts', { name, phone, relationship: relationship || null, notify_on_sos: notify });
      setName('');
      setPhone('');
      setRelationship('');
      contacts.reload();
    } catch (e) {
      setContactError(errorMessage(e));
    }
  }

  return (
    <Screen edges={['bottom']}>
      <Title>Safety</Title>
      <Section title="Safety PIN">
        <Muted>Your safety PIN cancels an SOS if you raise one by mistake. If you are ever forced to cancel, enter your duress PIN instead: it looks exactly like a normal cancel on screen, but our control room treats it as a live emergency.</Muted>
        <Gap h={space.md} />
        <Small color={colors.mist}>{user?.safety_pin_set ? 'Safety PIN is set.' : 'No safety PIN yet.'} {user?.duress_pin_set ? 'Duress PIN is set.' : ''}</Small>
        <Gap h={space.md} />
        <Row>
          <Field label={user?.safety_pin_set ? 'New safety PIN' : 'Safety PIN'} value={safetyPin} onChangeText={(t) => setSafetyPin(t.replace(/\D/g, '').slice(0, 4))} keyboardType="number-pad" secureTextEntry maxLength={4} style={{ flex: 1 }} />
          <Field label="Duress PIN" value={duressPin} onChangeText={(t) => setDuressPin(t.replace(/\D/g, '').slice(0, 4))} keyboardType="number-pad" secureTextEntry maxLength={4} style={{ flex: 1 }} />
        </Row>
        {pinError ? <Small color={colors.danger}>{pinError}</Small> : <Small>Four digits each. Choose numbers you will remember under stress, and don't use your bank PIN.</Small>}
        <Gap h={space.md} />
        <Button variant="line" title={saved ? 'Saved' : 'Save PINs'} onPress={savePins} loading={saving} disabled={safetyPin.length !== 4 || (duressPin.length > 0 && duressPin.length !== 4)} />
      </Section>

      <Section title="Emergency contacts">
        <Muted>People our control room may contact if you raise an SOS. Up to five.</Muted>
        <Gap h={space.md} />
        {contacts.data?.length ? (
          <Card style={{ padding: 0 }}>
            {contacts.data.map((c, i) => (
              <View key={c.id}>
                {i ? <Divider /> : null}
                <ListItem title={`${c.name}${c.relationship ? ` (${c.relationship})` : ''}`} sub={`${displayPhone(c.phone)}${c.notify_on_sos ? ', SMS on SOS' : ''}`} right={
                  <Button small variant="ghost" title="Remove" onPress={() => Alert.alert(`Remove ${c.name}?`, undefined, [{ text: 'Cancel', style: 'cancel' }, { text: 'Remove', style: 'destructive', onPress: async () => { await api.delete(`me/contacts/${c.id}`).catch(() => {}); contacts.reload(); } }])} />
                } />
              </View>
            ))}
          </Card>
        ) : <Muted>No emergency contacts yet.</Muted>}
        {(contacts.data?.length ?? 0) < 5 ? (
          <>
            <Gap h={space.lg} />
            <Label style={{ marginBottom: space.sm }}>Add a contact</Label>
            <Field label="Name" value={name} onChangeText={setName} />
            <Field label="Mobile number" value={phone} onChangeText={setPhone} keyboardType="phone-pad" />
            <Field label="Relationship (optional)" value={relationship} onChangeText={setRelationship} placeholder="e.g. Spouse" />
            <Check on={notify} onPress={() => setNotify(!notify)} label="Send them an SMS if I raise an SOS" />
            {contactError ? <Small color={colors.danger}>{contactError}</Small> : null}
            <Gap h={space.md} />
            <Button variant="line" title="Add contact" onPress={addContact} disabled={!name || !phone} />
          </>
        ) : null}
      </Section>
    </Screen>
  );
}
