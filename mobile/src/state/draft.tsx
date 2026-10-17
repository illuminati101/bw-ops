import React, { createContext, useContext, useMemo, useState } from 'react';
import type { Place, Service } from '../api/types';
import { fmt, saNow } from '../lib/format';

export type Draft = {
  service: Service | null;
  mode: 'advance' | 'on_demand';
  pickup: Place | null;
  dropoff: Place | null;
  stops: (Place & { wait_minutes: number })[];
  start: Date; // Cape Town wall-clock
  duration: number;
  schedule: { type: 'multi_day' | 'recurring'; start_date: string; end_date: string; start_time: string; end_time: string; weekdays: number[] };
  guards: number;
  vehicles: number;
  featureIds: number[];
  extraIds: number[];
  passengers: number;
  forSomeoneElse: boolean;
  principalName: string;
  principalPhone: string;
  notes: string;
  paymentMethod: 'card' | 'card_machine' | 'eft';
  paymentMethodId: number | null;
  companyId: number | null;
  preferredOperatorId: number | null;
};

export function defaultStart(minNoticeMinutes = 120) {
  const d = saNow();
  d.setMinutes(d.getMinutes() + Math.max(minNoticeMinutes, 60) + 30);
  d.setMinutes(Math.ceil(d.getMinutes() / 30) * 30, 0, 0);
  return d;
}

function initial(): Draft {
  const d = saNow();
  const s = new Date(d);
  s.setDate(s.getDate() + 3);
  const e = new Date(d);
  e.setDate(e.getDate() + 5);
  return {
    service: null, mode: 'advance', pickup: null, dropoff: null, stops: [], start: defaultStart(), duration: 120,
    schedule: { type: 'multi_day', start_date: fmt.ymd(s), end_date: fmt.ymd(e), start_time: '08:00', end_time: '18:00', weekdays: [1, 2, 3, 4, 5] },
    guards: 0, vehicles: 1, featureIds: [], extraIds: [], passengers: 1, forSomeoneElse: false, principalName: '', principalPhone: '', notes: '',
    paymentMethod: 'card', paymentMethodId: null, companyId: null, preferredOperatorId: null,
  };
}

export function toPayload(d: Draft) {
  const multi = d.service?.structure === 'multi_day';
  return {
    service_id: d.service?.id,
    mode: d.mode,
    starts_at: d.mode === 'advance' && !multi ? fmt.isoSA(d.start) : null,
    duration_minutes: multi ? null : d.duration,
    pickup: d.pickup,
    dropoff: d.service?.includes_transport ? d.dropoff : null,
    stops: d.service?.includes_transport ? d.stops.filter((s) => s.lat != null) : [],
    guards: d.guards,
    vehicles: d.vehicles,
    feature_ids: d.featureIds,
    extras: d.extraIds.map((id) => ({ id })),
    schedule: multi ? d.schedule : null,
    principal: d.forSomeoneElse ? { name: d.principalName, phone: d.principalPhone } : null,
    passengers: d.passengers,
    client_notes: d.notes || null,
    preferred_operator_id: d.preferredOperatorId,
    payment_method: d.paymentMethod,
    payment_method_id: d.paymentMethodId,
    company_id: d.companyId,
  };
}

type Ctx = { draft: Draft; set: (patch: Partial<Draft>) => void; reset: (patch?: Partial<Draft>) => void };
const DraftCtx = createContext<Ctx | null>(null);

export function DraftProvider({ children }: { children: React.ReactNode }) {
  const [draft, setDraft] = useState<Draft>(initial);
  const value = useMemo<Ctx>(() => ({
    draft,
    set: (patch) => setDraft((d) => ({ ...d, ...patch })),
    reset: (patch) => setDraft({ ...initial(), ...patch }),
  }), [draft]);
  return <DraftCtx.Provider value={value}>{children}</DraftCtx.Provider>;
}

export function useDraft() {
  const c = useContext(DraftCtx);
  if (!c) throw new Error('useDraft outside provider');
  return c;
}
