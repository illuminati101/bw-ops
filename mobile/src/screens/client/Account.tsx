import React from 'react';
import { Alert, Image, View } from 'react-native';
import * as WebBrowser from 'expo-web-browser';
import { api, errorMessage } from '../../api/client';
import { APP_VERSION } from '../../config';
import { displayPhone } from '../../lib/format';
import { useSession } from '../../state/session';
import { colors, space } from '../../theme';
import { Card, Divider, Gap, ListItem, Muted, Row, Screen, Small, Title } from '../../components/ui';

export default function Account({ navigation }: any) {
  const { user, config, signOut, refreshUser } = useSession();
  const v = user?.verification?.status;

  function deleteAccount() {
    Alert.alert('Delete your account?', 'This removes your profile, saved cards, emergency contacts and ID documents. Anonymised booking records are kept for tax purposes. This cannot be undone.', [
      { text: 'Keep my account', style: 'cancel' },
      {
        text: 'Delete', style: 'destructive', onPress: async () => {
          try {
            await api.delete('me');
            await signOut();
          } catch (e) {
            Alert.alert('Not deleted', errorMessage(e));
          }
        },
      },
    ]);
  }

  return (
    <Screen onRefresh={refreshUser}>
      <Row style={{ marginBottom: space.xl }}>
        {user?.avatar_url ? <Image source={{ uri: user.avatar_url }} style={{ width: 56, height: 56, borderRadius: 28 }} /> : (
          <View style={{ width: 56, height: 56, borderRadius: 28, backgroundColor: colors.graphite, alignItems: 'center', justifyContent: 'center' }}><Title style={{ color: colors.goldLight }}>{user?.name?.[0] ?? '?'}</Title></View>
        )}
        <View style={{ flex: 1 }}>
          <Title style={{ fontSize: 26 }}>{user?.name}</Title>
          <Muted>{displayPhone(user?.phone)}</Muted>
        </View>
      </Row>

      <Card style={{ padding: 0 }}>
        <ListItem icon="user" title="Your details" sub={user?.email ?? undefined} onPress={() => navigation.navigate('EditProfile')} />
        <Divider />
        <ListItem icon="credit-card" title="Payment" sub={user?.has_payment_method ? 'Card saved' : 'Add a card to book'} onPress={() => navigation.navigate('Payment')} />
        <Divider />
        {user?.verification?.required ? (
          <>
            <ListItem icon="shield" title="Verification" sub={v === 'verified' ? 'Verified' : v === 'pending' ? 'Under review' : v === 'rejected' ? 'Please try again' : 'Not verified yet'} onPress={() => navigation.navigate('Verify')} />
            <Divider />
          </>
        ) : null}
        <ListItem icon="lock" title="Safety PIN and emergency contacts" sub={user?.safety_pin_set ? 'Safety PIN set' : 'No safety PIN yet'} onPress={() => navigation.navigate('Safety')} />
      </Card>

      <Gap h={space.xl} />
      <Card style={{ padding: 0 }}>
        <ListItem icon="file-text" title="Terms of Service" onPress={() => config && WebBrowser.openBrowserAsync(config.legal.terms_url)} />
        <Divider />
        <ListItem icon="file-text" title="Privacy Policy" onPress={() => config && WebBrowser.openBrowserAsync(config.legal.privacy_url)} />
        <Divider />
        <ListItem icon="file-text" title="Cancellation Policy" onPress={() => config && WebBrowser.openBrowserAsync(config.legal.cancellation_url)} />
        <Divider />
        <ListItem icon="server" title="Server" onPress={() => navigation.navigate('ServerSettings')} />
      </Card>

      <Gap h={space.xl} />
      <Card style={{ padding: 0 }}>
        <ListItem icon="log-out" title="Sign out" onPress={signOut} right={<View />} />
        <Divider />
        <ListItem icon="trash-2" title="Delete account" danger onPress={deleteAccount} right={<View />} />
      </Card>
      <Gap />
      <Small center>{config?.brand.name ?? 'Black & White Ops'} {APP_VERSION}</Small>
    </Screen>
  );
}
