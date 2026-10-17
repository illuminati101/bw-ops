import React, { useState } from 'react';
import { Alert, View } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { api, errorMessage } from '../../api/client';
import { useLoader } from '../../lib/hooks';
import { fmt } from '../../lib/format';
import { colors, space } from '../../theme';
import { Button, Card, Chip, Divider, Field, Gap, Label, Muted, Row, Screen, Section, Small, StatusPill, Title } from '../../components/ui';

type Doc = { id: number; type: string; number: string | null; expires_on: string | null; status: string; valid: boolean; expiring_soon: boolean };
type DocType = { id: number; name: string; requires_expiry: boolean };

export default function Documents() {
  const { data, reload, refreshing } = useLoader(async () => api.get<{ types: DocType[]; documents: Doc[] }>('crew/documents'));
  const [typeId, setTypeId] = useState<number | null>(null);
  const [number, setNumber] = useState('');
  const [expires, setExpires] = useState('');
  const [file, setFile] = useState<{ uri: string; name: string; type: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const type = data?.types.find((t) => t.id === typeId);

  async function pick(source: 'camera' | 'library') {
    const perm = source === 'camera' ? await ImagePicker.requestCameraPermissionsAsync() : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) return Alert.alert('Permission needed', 'Allow access in Settings to add the document photo.');
    const r = source === 'camera' ? await ImagePicker.launchCameraAsync({ mediaTypes: ['images'], quality: 0.7 }) : await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.7 });
    if (r.canceled || !r.assets?.[0]) return;
    const a = r.assets[0];
    setFile({ uri: a.uri, name: a.fileName ?? 'document.jpg', type: a.mimeType ?? 'image/jpeg' });
  }

  async function upload() {
    if (!typeId || !file) return;
    setBusy(true);
    try {
      const form = new FormData();
      form.append('document_type_id', String(typeId));
      form.append('file', file as any);
      if (number) form.append('number', number);
      if (expires) form.append('expires_on', expires);
      await api.upload('crew/documents', form);
      setTypeId(null);
      setNumber('');
      setExpires('');
      setFile(null);
      reload();
      Alert.alert('Uploaded', 'The operations team will review it.');
    } catch (e) {
      Alert.alert('Not uploaded', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Screen onRefresh={reload} refreshing={refreshing}>
      <Title>Documents</Title>
      <Muted style={{ marginTop: space.sm }}>Keep these current. If a required document expires, you come off the roster for the work it covers until it is renewed.</Muted>
      <Section>
        {data?.documents.length ? (
          <Card style={{ padding: 0 }}>
            {data.documents.map((d, i) => (
              <View key={d.id}>
                {i ? <Divider /> : null}
                <Row style={{ padding: space.lg, justifyContent: 'space-between' }}>
                  <View style={{ flex: 1 }}>
                    <Label>{d.type}</Label>
                    <Small>{[d.number, d.expires_on ? `Expires ${fmt.date(d.expires_on + 'T12:00:00+02:00')}` : 'No expiry'].filter(Boolean).join(', ')}</Small>
                  </View>
                  <StatusPill label={d.status === 'pending' ? 'In review' : !d.valid ? 'Expired' : d.expiring_soon ? 'Expiring soon' : 'Valid'} tone={d.status === 'pending' ? 'plain' : !d.valid ? 'alert' : d.expiring_soon ? 'gold' : 'ok'} />
                </Row>
              </View>
            ))}
          </Card>
        ) : <Muted>No documents on file yet.</Muted>}
      </Section>

      <Section title="Upload a document">
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: space.sm }}>
          {data?.types.map((t) => <Chip key={t.id} label={t.name} on={typeId === t.id} onPress={() => setTypeId(t.id)} />)}
        </View>
        {typeId ? (
          <>
            <Gap />
            <Field label="Certificate or registration number (optional)" value={number} onChangeText={setNumber} />
            {type?.requires_expiry ? <Field label="Expiry date" value={expires} onChangeText={setExpires} placeholder="YYYY-MM-DD" keyboardType="numbers-and-punctuation" /> : null}
            <Row>
              <Button small variant="line" icon="camera" title="Take photo" onPress={() => pick('camera')} style={{ flex: 1 }} />
              <Button small variant="line" icon="upload" title="Choose photo" onPress={() => pick('library')} style={{ flex: 1 }} />
            </Row>
            {file ? <Small color={colors.goldLight} style={{ marginTop: space.sm }}>Photo added: {file.name}</Small> : null}
            <Gap />
            <Button title="Upload for review" onPress={upload} loading={busy} disabled={!file || (!!type?.requires_expiry && !/^\d{4}-\d{2}-\d{2}$/.test(expires))} />
          </>
        ) : null}
      </Section>
    </Screen>
  );
}
