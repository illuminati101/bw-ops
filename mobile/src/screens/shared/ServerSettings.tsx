import React, { useState } from 'react';
import { Alert } from 'react-native';
import { api, normaliseServer } from '../../api/client';
import { DEFAULT_SERVER, APP_VERSION, VARIANT } from '../../config';
import { useSession } from '../../state/session';
import { space } from '../../theme';
import { Banner, Button, Field, Gap, Muted, Screen, Small, Title } from '../../components/ui';

/** Test builds talk to whatever server is running (e.g. a tunnel to the office PC). The address can change, so it's editable. */
export default function ServerSettings({ navigation }: any) {
  const { server, setServer, token } = useSession();
  const [url, setUrl] = useState(server);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function save(target = url) {
    setError(null);
    setBusy(true);
    const candidate = normaliseServer(target);
    try {
      const res = await fetch(`${candidate}/api/v1/config`, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error(`The server answered with status ${res.status}.`);
      const json = await res.json();
      if (!json?.brand) throw new Error('That address does not look like a Black & White Ops server.');
    } catch (e: any) {
      setBusy(false);
      setError(e?.message?.includes('Network') ? 'Cannot reach that address. Check it is typed correctly and the server is running.' : e?.message ?? 'Cannot reach that address.');
      return;
    }
    const apply = async () => {
      await setServer(candidate);
      setBusy(false);
      navigation.goBack();
    };
    if (token && candidate !== api.server) {
      Alert.alert('Switch server?', 'You will be signed out and need to sign in again on the new server.', [
        { text: 'Cancel', style: 'cancel', onPress: () => setBusy(false) },
        { text: 'Switch', style: 'destructive', onPress: apply },
      ]);
    } else {
      await apply();
    }
  }

  return (
    <Screen edges={['bottom']}>
      <Title>Server</Title>
      <Muted style={{ marginTop: space.sm }}>The address of the Black & White Ops system this app talks to. During testing this is usually a tunnel address such as https://something.trycloudflare.com.</Muted>
      <Gap h={space.xl} />
      <Field label="Server address" value={url} onChangeText={setUrl} autoCapitalize="none" autoCorrect={false} keyboardType="url" placeholder="https://blackandwhiteops.com" error={error} />
      <Button title="Save and connect" onPress={() => save()} loading={busy} disabled={!url.trim()} />
      <Gap h={space.md} />
      {DEFAULT_SERVER !== server ? <Button variant="line" title="Reset to default" onPress={() => { setUrl(DEFAULT_SERVER); save(DEFAULT_SERVER); }} /> : null}
      <Gap h={space.xl} />
      <Banner tone="plain" text={`Default: ${DEFAULT_SERVER}\nApp: ${VARIANT} ${APP_VERSION}`} />
      <Gap />
      <Small>Only change this if the Black & White Ops team asks you to.</Small>
    </Screen>
  );
}
