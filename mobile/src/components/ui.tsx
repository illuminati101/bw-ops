import React from 'react';
import {
  ActivityIndicator, Image, Pressable, RefreshControl, ScrollView, StyleSheet, Text, TextInput, TextInputProps, TextProps, View, ViewStyle, StyleProp,
} from 'react-native';
import { SafeAreaView, Edge } from 'react-native-safe-area-context';
import Feather from '@expo/vector-icons/Feather';
import * as Haptics from 'expo-haptics';
import { colors, fonts, radius, space } from '../theme';

export type IconName = React.ComponentProps<typeof Feather>['name'];
export const Icon = ({ name, size = 18, color = colors.bone }: { name: IconName; size?: number; color?: string }) => <Feather name={name} size={size} color={color} />;

// ── Text ────────────────────────────────────────────────────────────
type TP = TextProps & { children?: React.ReactNode; color?: string; center?: boolean };
const mk = (base: object) => ({ style, color, center, ...p }: TP) => (
  <Text {...p} style={[base, color ? { color } : null, center ? { textAlign: 'center' } : null, style]} />
);
export const Display = mk({ fontFamily: fonts.display, fontSize: 40, lineHeight: 44, color: colors.bone, letterSpacing: -0.3 });
export const Title = mk({ fontFamily: fonts.display, fontSize: 32, lineHeight: 36, color: colors.bone, letterSpacing: -0.2 });
export const H2 = mk({ fontFamily: fonts.display, fontSize: 24, lineHeight: 28, color: colors.bone });
export const Label = mk({ fontFamily: fonts.semibold, fontSize: 15, lineHeight: 20, color: colors.bone });
export const Body = mk({ fontFamily: fonts.body, fontSize: 15, lineHeight: 22, color: colors.bone });
export const Muted = mk({ fontFamily: fonts.body, fontSize: 14, lineHeight: 20, color: colors.ash });
export const Small = mk({ fontFamily: fonts.body, fontSize: 12.5, lineHeight: 17, color: colors.ash });
export const Money = mk({ fontFamily: fonts.display, fontSize: 34, lineHeight: 38, color: colors.goldLight });

// ── Layout ──────────────────────────────────────────────────────────
export function Screen({ children, scroll = true, refreshing, onRefresh, edges = ['top'], padded = true, footer, contentStyle }: {
  children: React.ReactNode; scroll?: boolean; refreshing?: boolean; onRefresh?: () => void; edges?: Edge[]; padded?: boolean; footer?: React.ReactNode; contentStyle?: StyleProp<ViewStyle>;
}) {
  const pad = padded ? { paddingHorizontal: space.lg, paddingBottom: space.xxl } : null;
  return (
    <SafeAreaView edges={edges} style={s.screen}>
      {scroll ? (
        <ScrollView
          contentContainerStyle={[pad, { paddingTop: space.md }, contentStyle]}
          keyboardShouldPersistTaps="handled"
          refreshControl={onRefresh ? <RefreshControl tintColor={colors.gold} refreshing={!!refreshing} onRefresh={onRefresh} /> : undefined}
        >
          {children}
        </ScrollView>
      ) : (
        <View style={[{ flex: 1 }, pad, contentStyle]}>{children}</View>
      )}
      {footer}
    </SafeAreaView>
  );
}

export const Gap = ({ h = space.lg }: { h?: number }) => <View style={{ height: h }} />;
export const Divider = ({ style }: { style?: StyleProp<ViewStyle> }) => <View style={[{ height: StyleSheet.hairlineWidth, backgroundColor: colors.lineStrong }, style]} />;
export const Row = ({ children, style, gap = space.md }: { children: React.ReactNode; style?: StyleProp<ViewStyle>; gap?: number }) => (
  <View style={[{ flexDirection: 'row', alignItems: 'center', gap }, style]}>{children}</View>
);

export function Card({ children, style, onPress, tone }: { children: React.ReactNode; style?: StyleProp<ViewStyle>; onPress?: () => void; tone?: 'gold' | 'danger' }) {
  const toneStyle = tone === 'gold' ? s.cardGold : tone === 'danger' ? s.cardDanger : null;
  if (onPress) {
    return (
      <Pressable onPress={onPress} style={({ pressed }) => [s.card, toneStyle, pressed && { opacity: 0.85 }, style]} accessibilityRole="button">
        {children}
      </Pressable>
    );
  }
  return <View style={[s.card, toneStyle, style]}>{children}</View>;
}

export function Section({ title, children, action }: { title?: string; children: React.ReactNode; action?: React.ReactNode }) {
  return (
    <View style={{ marginTop: space.xl }}>
      {title ? (
        <Row style={{ justifyContent: 'space-between', marginBottom: space.md }}>
          <Label>{title}</Label>
          {action}
        </Row>
      ) : null}
      {children}
    </View>
  );
}

// ── Controls ────────────────────────────────────────────────────────
export function Button({ title, onPress, variant = 'gold', icon, loading, disabled, style, small, haptic }: {
  title: string; onPress?: () => void; variant?: 'gold' | 'line' | 'ghost' | 'danger' | 'dangerSolid'; icon?: IconName; loading?: boolean; disabled?: boolean; style?: StyleProp<ViewStyle>; small?: boolean; haptic?: boolean;
}) {
  const v = btn[variant];
  const off = disabled || loading;
  return (
    <Pressable
      onPress={() => {
        if (haptic) Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
        onPress?.();
      }}
      disabled={off}
      accessibilityRole="button"
      accessibilityState={{ disabled: !!off, busy: !!loading }}
      style={({ pressed }) => [s.btn, small && s.btnSmall, v.box, pressed && !off && { opacity: 0.8 }, off && { opacity: 0.45 }, style]}
    >
      {loading ? <ActivityIndicator color={v.text} /> : (
        <>
          {icon ? <Feather name={icon} size={small ? 15 : 17} color={v.text} /> : null}
          <Text style={[s.btnText, small && { fontSize: 14 }, { color: v.text }]}>{title}</Text>
        </>
      )}
    </Pressable>
  );
}

const btn = {
  gold: { box: { backgroundColor: colors.gold }, text: colors.night },
  line: { box: { borderWidth: 1, borderColor: colors.lineStrong }, text: colors.bone },
  ghost: { box: {}, text: colors.mist },
  danger: { box: { borderWidth: 1, borderColor: 'rgba(229,130,111,0.55)', backgroundColor: colors.dangerWash }, text: '#f3c3b8' },
  dangerSolid: { box: { backgroundColor: colors.dangerStrong }, text: '#fff' },
};

export function Field({ label, hint, error, style, ...p }: TextInputProps & { label?: string; hint?: string; error?: string | null }) {
  return (
    <View style={[{ marginBottom: space.lg }, style as any]}>
      {label ? <Text style={s.fieldLabel}>{label}</Text> : null}
      <TextInput placeholderTextColor={colors.ash} selectionColor={colors.gold} {...p} style={[s.input, p.multiline && { minHeight: 90, textAlignVertical: 'top', paddingTop: 12 }, error ? { borderColor: colors.danger } : null]} />
      {error ? <Text style={s.error}>{error}</Text> : hint ? <Text style={s.hint}>{hint}</Text> : null}
    </View>
  );
}

export function Segmented<T extends string>({ value, options, onChange }: { value: T; options: { value: T; label: string }[]; onChange: (v: T) => void }) {
  return (
    <View style={s.seg} accessibilityRole="tablist">
      {options.map((o) => {
        const on = o.value === value;
        return (
          <Pressable key={o.value} onPress={() => onChange(o.value)} style={[s.segItem, on && s.segOn]} accessibilityRole="tab" accessibilityState={{ selected: on }}>
            <Text style={[s.segText, on && { color: colors.night }]}>{o.label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

export function Chip({ label, on, onPress, note }: { label: string; on: boolean; onPress: () => void; note?: string }) {
  return (
    <Pressable onPress={onPress} style={[s.chip, on && s.chipOn]} accessibilityRole="checkbox" accessibilityState={{ checked: on }}>
      <Text style={[s.chipText, on && { color: colors.goldLight }]}>{label}</Text>
      {note ? <Text style={[s.chipText, { color: colors.ash }]}>{note}</Text> : null}
    </Pressable>
  );
}

export function Stepper({ label, value, min, max, onChange }: { label: string; value: number; min: number; max: number; onChange: (n: number) => void }) {
  return (
    <View>
      <Text style={s.fieldLabel}>{label}</Text>
      <Row gap={space.sm}>
        <Pressable style={s.stepBtn} onPress={() => onChange(Math.max(min, value - 1))} disabled={value <= min} accessibilityLabel={`Fewer ${label.toLowerCase()}`}>
          <Feather name="minus" size={16} color={value <= min ? colors.ash : colors.bone} />
        </Pressable>
        <Text style={s.stepValue}>{value}</Text>
        <Pressable style={s.stepBtn} onPress={() => onChange(Math.min(max, value + 1))} disabled={value >= max} accessibilityLabel={`More ${label.toLowerCase()}`}>
          <Feather name="plus" size={16} color={value >= max ? colors.ash : colors.bone} />
        </Pressable>
      </Row>
    </View>
  );
}

export function Radio({ label, sub, on, onPress }: { label: string; sub?: string; on: boolean; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={[s.radio, on && { borderColor: 'rgba(201,168,106,0.6)', backgroundColor: colors.goldWash }]} accessibilityRole="radio" accessibilityState={{ checked: on }}>
      <View style={[s.radioDot, on && { borderColor: colors.gold }]}>{on ? <View style={s.radioInner} /> : null}</View>
      <View style={{ flex: 1 }}>
        <Label>{label}</Label>
        {sub ? <Muted>{sub}</Muted> : null}
      </View>
    </Pressable>
  );
}

export function Check({ label, on, onPress }: { label: React.ReactNode; on: boolean; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={{ flexDirection: 'row', gap: space.md, alignItems: 'flex-start', paddingVertical: 6 }} accessibilityRole="checkbox" accessibilityState={{ checked: on }}>
      <View style={[s.check, on && { backgroundColor: colors.gold, borderColor: colors.gold }]}>{on ? <Feather name="check" size={14} color={colors.night} /> : null}</View>
      <View style={{ flex: 1 }}>{typeof label === 'string' ? <Body>{label}</Body> : label}</View>
    </Pressable>
  );
}

export function Banner({ text, title, tone = 'gold', action }: { text: string; title?: string; tone?: 'gold' | 'danger' | 'plain'; action?: React.ReactNode }) {
  const style = tone === 'danger' ? s.cardDanger : tone === 'gold' ? s.cardGold : null;
  return (
    <View style={[s.card, style, { gap: space.sm }]} accessibilityRole={tone === 'danger' ? 'alert' : undefined}>
      {title ? <Label>{title}</Label> : null}
      <Muted color={tone === 'plain' ? colors.ash : colors.mist}>{text}</Muted>
      {action}
    </View>
  );
}

export function Empty({ title, text, action }: { title: string; text?: string; action?: React.ReactNode }) {
  return (
    <View style={s.empty}>
      <H2 center>{title}</H2>
      {text ? <Muted center style={{ marginTop: space.sm }}>{text}</Muted> : null}
      {action ? <View style={{ marginTop: space.lg, alignSelf: 'stretch' }}>{action}</View> : null}
    </View>
  );
}

export function Loading() {
  return (
    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.night, padding: space.xxl }}>
      <ActivityIndicator color={colors.gold} />
    </View>
  );
}

export function ListItem({ icon, title, sub, onPress, right, danger }: { icon?: IconName; title: string; sub?: string; onPress?: () => void; right?: React.ReactNode; danger?: boolean }) {
  return (
    <Pressable onPress={onPress} disabled={!onPress} style={({ pressed }) => [s.listItem, pressed && { backgroundColor: colors.graphite }]} accessibilityRole={onPress ? 'button' : undefined}>
      {icon ? <Feather name={icon} size={18} color={danger ? colors.danger : colors.gold} /> : null}
      <View style={{ flex: 1 }}>
        <Body color={danger ? colors.danger : undefined}>{title}</Body>
        {sub ? <Small>{sub}</Small> : null}
      </View>
      {right ?? (onPress ? <Feather name="chevron-right" size={18} color={colors.ash} /> : null)}
    </Pressable>
  );
}

export function StatusPill({ label, tone = 'plain' }: { label: string; tone?: 'gold' | 'ok' | 'alert' | 'plain' }) {
  const c = tone === 'gold' ? colors.goldLight : tone === 'ok' ? colors.ok : tone === 'alert' ? colors.danger : colors.mist;
  return (
    <View style={[s.pill, { borderColor: c + '66' }]}>
      <Text style={[s.pillText, { color: c }]}>{label}</Text>
    </View>
  );
}

export const s = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.night },
  card: { backgroundColor: colors.carbon, borderRadius: radius.lg, borderWidth: StyleSheet.hairlineWidth, borderColor: colors.lineStrong, padding: space.lg },
  cardGold: { borderColor: 'rgba(201,168,106,0.45)', backgroundColor: colors.goldWash },
  cardDanger: { borderColor: 'rgba(229,130,111,0.55)', backgroundColor: colors.dangerWash },
  btn: { minHeight: 52, borderRadius: radius.md, paddingHorizontal: space.xl, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: space.sm },
  btnSmall: { minHeight: 40, paddingHorizontal: space.lg },
  btnText: { fontFamily: fonts.semibold, fontSize: 16 },
  fieldLabel: { fontFamily: fonts.medium, fontSize: 13.5, color: colors.mist, marginBottom: 6 },
  input: { minHeight: 52, borderRadius: radius.md, backgroundColor: colors.graphite, borderWidth: 1, borderColor: colors.line, paddingHorizontal: space.lg, color: colors.bone, fontFamily: fonts.body, fontSize: 16 },
  error: { fontFamily: fonts.medium, fontSize: 13, color: colors.danger, marginTop: 6 },
  hint: { fontFamily: fonts.body, fontSize: 13, color: colors.ash, marginTop: 6 },
  seg: { flexDirection: 'row', backgroundColor: colors.graphite, borderRadius: radius.pill, padding: 4 },
  segItem: { flex: 1, paddingVertical: 10, borderRadius: radius.pill, alignItems: 'center' },
  segOn: { backgroundColor: colors.bone },
  segText: { fontFamily: fonts.semibold, fontSize: 14, color: colors.mist },
  chip: { flexDirection: 'row', gap: 6, borderWidth: 1, borderColor: colors.lineStrong, borderRadius: radius.pill, paddingHorizontal: 14, paddingVertical: 8 },
  chipOn: { borderColor: colors.gold, backgroundColor: colors.goldWash },
  chipText: { fontFamily: fonts.medium, fontSize: 14, color: colors.bone },
  stepBtn: { width: 44, height: 44, borderRadius: radius.md, borderWidth: 1, borderColor: colors.lineStrong, alignItems: 'center', justifyContent: 'center' },
  stepValue: { minWidth: 34, textAlign: 'center', fontFamily: fonts.semibold, fontSize: 20, color: colors.bone },
  radio: { flexDirection: 'row', gap: space.md, alignItems: 'center', borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: space.lg, marginBottom: space.sm },
  radioDot: { width: 20, height: 20, borderRadius: 10, borderWidth: 2, borderColor: colors.ash, alignItems: 'center', justifyContent: 'center' },
  radioInner: { width: 10, height: 10, borderRadius: 5, backgroundColor: colors.gold },
  check: { width: 22, height: 22, borderRadius: 6, borderWidth: 1.5, borderColor: colors.ash, alignItems: 'center', justifyContent: 'center', marginTop: 1 },
  empty: { alignItems: 'center', paddingVertical: 48, paddingHorizontal: space.xl, borderRadius: radius.lg, borderWidth: 1, borderStyle: 'dashed', borderColor: colors.lineStrong },
  listItem: { flexDirection: 'row', alignItems: 'center', gap: space.md, paddingVertical: 14, paddingHorizontal: space.lg },
  pill: { borderWidth: 1, borderRadius: radius.pill, paddingHorizontal: 10, paddingVertical: 3, alignSelf: 'flex-start' },
  pillText: { fontFamily: fonts.medium, fontSize: 12.5 },
});

/** Brand lock-up, matching the website header: gold crosshair mark with the wordmark. */
export function Logo({ size = 1 }: { size?: number }) {
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 * size }} accessibilityRole="image" accessibilityLabel="Black & White Ops">
      <Image source={require('../../assets/brand/mark-gold.png')} style={{ width: 40 * size, height: 40 * size }} />
      <View>
        <Text style={{ fontFamily: fonts.semibold, fontSize: 10 * size, letterSpacing: 3.2 * size, color: colors.mist }}>BLACK & WHITE</Text>
        <Text style={{ fontFamily: fonts.display, fontSize: 25 * size, letterSpacing: 4 * size, color: colors.bone, marginTop: 1 }}>OPS</Text>
      </View>
    </View>
  );
}
