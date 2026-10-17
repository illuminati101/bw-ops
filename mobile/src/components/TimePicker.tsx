import React, { useMemo } from 'react';
import { FlatList, Pressable, Text, View } from 'react-native';
import { colors, fonts, radius, space } from '../theme';
import { saNow } from '../lib/format';

const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const pad = (n: number) => String(n).padStart(2, '0');
const sameDay = (a: Date, b: Date) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

/** Day strip plus time slots, in Cape Town time. `value` is a Cape Town wall-clock Date. */
export function DayStrip({ value, onChange, daysAhead, minDate }: { value: Date; onChange: (d: Date) => void; daysAhead: number; minDate?: Date }) {
  const days = useMemo(() => {
    const out: Date[] = [];
    const start = new Date(minDate ?? saNow());
    start.setHours(0, 0, 0, 0);
    for (let i = 0; i <= Math.min(daysAhead, 90); i++) {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      out.push(d);
    }
    return out;
  }, [daysAhead, minDate]);
  const today = saNow();
  return (
    <FlatList
      horizontal
      data={days}
      keyExtractor={(d) => d.toDateString()}
      showsHorizontalScrollIndicator={false}
      initialScrollIndex={Math.max(0, days.findIndex((d) => sameDay(d, value)) - 1)}
      getItemLayout={(_, i) => ({ length: 64, offset: 64 * i, index: i })}
      renderItem={({ item }) => {
        const on = sameDay(item, value);
        return (
          <Pressable
            onPress={() => { const d = new Date(item); d.setHours(value.getHours(), value.getMinutes(), 0, 0); onChange(d); }}
            style={{ width: 56, marginRight: 8, paddingVertical: 10, borderRadius: radius.md, alignItems: 'center', borderWidth: 1, borderColor: on ? colors.gold : colors.line, backgroundColor: on ? colors.goldWash : colors.carbon }}
            accessibilityRole="button" accessibilityState={{ selected: on }}
          >
            <Text style={{ fontFamily: fonts.medium, fontSize: 12, color: on ? colors.goldLight : colors.ash }}>{sameDay(item, today) ? 'Today' : DAYS[item.getDay()]}</Text>
            <Text style={{ fontFamily: fonts.semibold, fontSize: 20, color: colors.bone, marginTop: 2 }}>{item.getDate()}</Text>
            <Text style={{ fontFamily: fonts.body, fontSize: 11, color: colors.ash }}>{MONTHS[item.getMonth()]}</Text>
          </Pressable>
        );
      }}
    />
  );
}

export function TimeSlots({ value, onChange, earliest, step = 30 }: { value: Date; onChange: (d: Date) => void; earliest?: Date; step?: number }) {
  const slots = useMemo(() => {
    const out: Date[] = [];
    const base = new Date(value);
    base.setHours(0, 0, 0, 0);
    for (let m = 0; m < 24 * 60; m += step) {
      const d = new Date(base.getTime() + m * 60000);
      if (!earliest || d >= earliest) out.push(d);
    }
    return out;
  }, [value.toDateString(), earliest?.getTime(), step]);
  const idx = slots.findIndex((d) => d.getHours() === value.getHours() && d.getMinutes() === value.getMinutes());
  if (!slots.length) return <Text style={{ fontFamily: fonts.body, color: colors.ash }}>No times left today. Choose another day.</Text>;
  return (
    <FlatList
      horizontal
      data={slots}
      keyExtractor={(d) => String(d.getTime())}
      showsHorizontalScrollIndicator={false}
      initialScrollIndex={Math.max(0, idx - 1)}
      getItemLayout={(_, i) => ({ length: 78, offset: 78 * i, index: i })}
      renderItem={({ item }) => {
        const on = item.getHours() === value.getHours() && item.getMinutes() === value.getMinutes();
        return (
          <Pressable onPress={() => onChange(item)} style={{ width: 70, marginRight: 8, paddingVertical: 12, borderRadius: radius.md, alignItems: 'center', borderWidth: 1, borderColor: on ? colors.gold : colors.line, backgroundColor: on ? colors.goldWash : colors.carbon }} accessibilityRole="button" accessibilityState={{ selected: on }}>
            <Text style={{ fontFamily: fonts.semibold, fontSize: 16, color: on ? colors.goldLight : colors.bone }}>{pad(item.getHours())}:{pad(item.getMinutes())}</Text>
          </Pressable>
        );
      }}
    />
  );
}

export function ChoiceRow<T extends string | number>({ value, options, onChange }: { value: T; options: { value: T; label: string }[]; onChange: (v: T) => void }) {
  return (
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: space.sm }}>
      {options.map((o) => {
        const on = o.value === value;
        return (
          <Pressable key={String(o.value)} onPress={() => onChange(o.value)} style={{ paddingHorizontal: 14, paddingVertical: 9, borderRadius: radius.pill, borderWidth: 1, borderColor: on ? colors.gold : colors.lineStrong, backgroundColor: on ? colors.goldWash : 'transparent' }} accessibilityRole="button" accessibilityState={{ selected: on }}>
            <Text style={{ fontFamily: fonts.medium, fontSize: 14, color: on ? colors.goldLight : colors.bone }}>{o.label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}
