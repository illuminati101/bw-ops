import React, { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, View } from 'react-native';
import * as Device from 'expo-device';
import * as WebBrowser from 'expo-web-browser';
import { api, ApiError, errorMessage } from '../../api/client';
import { IS_CREW } from '../../config';
import { useSession } from '../../state/session';
import { colors, space } from '../../theme';
import { Banner, Button, Field, Gap, Logo, Muted, Screen, Small, Title } from '../../components/ui';

export default function Login({ navigation }: any) {
  const { signIn, config, configError, server, refreshConfig } = useSession();
  const [step, setStep] = useState<'phone' | 'code'>('phone');
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [devCode, setDevCode] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const app = IS_CREW ? 'crew' : 'client';

  async function send() {
    setError(null);
    setBusy(true);
    try {
      const r = await api.post('auth/otp/request', { phone, app });
      setDevCode(r.dev_code ?? null);
      setStep('code');
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  async function verify() {
    setError(null);
    setBusy(true);
    try {
      const r = await api.post('auth/otp/verify', { phone, code, app, device_name: Device.modelName ?? Platform.OS });
      await signIn(r.token, r.user, !!r.needs_profile);
    } catch (e) {
      setError(e instanceof ApiError && e.field('code') ? e.field('code')! : errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Screen edges={['top', 'bottom']}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={{ alignItems: 'flex-start', marginTop: space.xxl }}>
          <Logo />
        </View>
        <Gap h={space.xxl} />
        <Title>{IS_CREW ? 'Crew sign in' : step === 'phone' ? 'Sign in or create an account' : 'Enter your code'}</Title>
        <Muted style={{ marginTop: space.sm }}>
          {step === 'phone'
            ? IS_CREW ? 'Use the mobile number the operations team registered for you.' : 'We will send a one-time code to your mobile number. No passwords.'
            : `We sent a 6-digit code to ${phone}.`}
        </Muted>
        <Gap h={space.xl} />

        {configError && !config ? (
          <>
            <Banner tone="danger" title="Cannot reach the server" text={`${configError}\n\nServer: ${server}`} action={<Button small variant="line" title="Try again" onPress={refreshConfig} />} />
            <Gap />
          </>
        ) : null}

        {step === 'phone' ? (
          <>
            <Field label="Mobile number" value={phone} onChangeText={setPhone} keyboardType="phone-pad" autoComplete="tel" textContentType="telephoneNumber" placeholder="082 123 4567" error={error} autoFocus />
            <Button title="Send code" onPress={send} loading={busy} disabled={phone.replace(/\D/g, '').length < 9} />
          </>
        ) : (
          <>
            {devCode ? <><Banner text={`Test mode: SMS isn't connected yet, so your code is ${devCode}.`} /><Gap h={space.md} /></> : null}
            <Field label="Code" value={code} onChangeText={(t) => setCode(t.replace(/\D/g, '').slice(0, 6))} keyboardType="number-pad" autoComplete="sms-otp" textContentType="oneTimeCode" maxLength={6} error={error} autoFocus />
            <Button title="Continue" onPress={verify} loading={busy} disabled={code.length < 4} />
            <Gap h={space.md} />
            <Button variant="ghost" title="Use a different number" onPress={() => { setStep('phone'); setCode(''); setError(null); }} />
            <Button variant="ghost" title="Send a new code" onPress={send} />
          </>
        )}

        <Gap h={space.xxl} />
        {!IS_CREW && config ? (
          <Small>
            By continuing you agree to our{' '}
            <Small color={colors.mist} onPress={() => WebBrowser.openBrowserAsync(config.legal.terms_url)} style={{ textDecorationLine: 'underline' }}>Terms of Service</Small>
            {' '}and{' '}
            <Small color={colors.mist} onPress={() => WebBrowser.openBrowserAsync(config.legal.privacy_url)} style={{ textDecorationLine: 'underline' }}>Privacy Policy</Small>.
          </Small>
        ) : null}
        <Pressable onPress={() => navigation.navigate('ServerSettings')} style={{ marginTop: space.xl, paddingVertical: 8 }} accessibilityRole="button">
          <Small>Connected to {server.replace(/^https?:\/\//, '')}. Change server</Small>
        </Pressable>
      </KeyboardAvoidingView>
    </Screen>
  );
}
