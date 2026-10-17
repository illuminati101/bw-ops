export type Place = { address: string; lat: number; lng: number; label?: string; place_id?: string };

export type AppConfig = {
  brand: { name: string; phone: string; control_room_phone: string; email: string; website: string };
  region: { id: number; name: string; lat: number; lng: number; radius_km: number };
  features: Record<string, boolean>;
  booking: { notice_minutes: number; notice_minutes_team: number; max_days_ahead: number; reconfirm_hours: number };
  cancellation: { tiers: { hours: number; percent: number }[]; on_demand_free_minutes: number; no_show_percent: number; no_show_minimum: number };
  filters: { id: number; label: string; applies_to: string; category: string; description?: string; surcharge: { type: string; amount: number } | null }[];
  maps: { provider: string; tile_url: string };
  payments: { gateway: string; record_only: boolean };
  sms_test_mode: boolean;
  legal: { terms_url: string; privacy_url: string; cancellation_url: string; terms_version: string };
  apps: { min_version_client?: string; min_version_crew?: string };
};

export type User = {
  id: number;
  name: string;
  first_name: string;
  email: string | null;
  phone: string;
  type: string;
  avatar_url: string | null;
  marketing_opt_in: boolean;
  terms_accepted: boolean;
  verification: { status: string; required: boolean; note: string | null } | null;
  trusted: boolean;
  code_name: string | null;
  companies: { id: number; name: string; role: string; eft: boolean }[];
  eft_approved: boolean;
  has_payment_method: boolean;
  safety_pin_set: boolean;
  duress_pin_set: boolean;
};

export type Service = {
  id: number;
  slug: string;
  name: string;
  tagline: string | null;
  summary: string | null;
  description: string | null;
  highlights: string[];
  icon: string | null;
  includes_transport: boolean;
  includes_protection: boolean;
  default_guards: number;
  default_vehicles: number;
  allows_on_demand: boolean;
  allows_advance: boolean;
  structure: 'single' | 'multi_day';
  min_notice_minutes: number;
  min_duration_minutes: number;
  custom_quote: boolean;
  from_price: number | null;
  per_hour: number | null;
  extras: { id: number; name: string; description: string | null; pricing: string; amount: number }[];
};

export type Quote = {
  lines: { label: string; amount: number }[];
  subtotal: number;
  surcharge: { label: string; amount: number } | null;
  total: number;
  vat_included: number;
  overtime_rate: number;
  no_show_fee: number;
  hold_amount: number;
  custom_quote: boolean;
  estimated_km: number | null;
  duration_minutes: number;
};

export type OperatorPublic = {
  assignment_id: number;
  role: string;
  role_label: string;
  status: string;
  status_label: string;
  first_name: string;
  call_sign: string | null;
  photo_url: string | null;
  vehicle: { label: string; registration: string; class: string; armoured: boolean } | null;
  location: { lat: number; lng: number; heading: number | null; at: string | null } | null;
};

export type Booking = {
  id: number;
  reference: string;
  status: string;
  status_label: string;
  is_closed: boolean;
  mode: 'advance' | 'on_demand';
  structure: string;
  service: { id: number; name: string; slug: string; icon: string | null; includes_transport: boolean };
  starts_at: string;
  ends_at: string;
  duration_minutes: number;
  pickup: Place;
  dropoff: Place | null;
  stops: (Place & { wait_minutes: number })[];
  shifts: { starts_at: string; ends_at: string; status: string }[];
  principal: { name: string; phone: string } | null;
  passengers: number;
  guards: number;
  vehicles: number;
  requirements: string[];
  notes: string | null;
  quote: {
    total: number;
    final_total: number | null;
    lines: { label: string; amount: number }[];
    surcharge: { label: string; amount: number } | null;
    custom_note: string | null;
    overtime_rate: number;
    no_show_fee: number | null;
    hold_amount: number;
    awaiting_quote: boolean;
    awaiting_acceptance: boolean;
  };
  payment: { method: string; status: string; card: string | null; cancellation_fee: number | null };
  arrival_pin: string | null;
  pin_verified: boolean;
  operators: OperatorPublic[];
  eta_minutes: number | null;
  reconfirm: { required: boolean; due_at: string | null; done: boolean };
  can: { cancel: boolean; accept_quote: boolean; chat: boolean; call: boolean; share: boolean; rate: boolean; sos: boolean };
  timeline: { type: string; summary: string; at: string }[];
};

export type ChatMessage = { id: number; mine: boolean; sender_role: string; body: string; created_at: string };

export type Job = {
  id: number;
  booking_id: number;
  reference: string;
  status: string;
  status_label: string;
  booking_status: string;
  role: string;
  role_label: string;
  service: string;
  mode: string;
  starts_at: string;
  ends_at: string;
  payout_amount: number | null;
  live: boolean;
  principal?: { name: string; photo_url: string | null; passengers: number };
  pickup?: Place;
  dropoff?: Place | null;
  stops?: (Place & { wait_minutes: number })[];
  duration_minutes?: number;
  requirements?: string[];
  notes?: string | null;
  vehicle?: string | null;
  team?: { name: string; call_sign: string; role: string }[];
  distance_km?: number | null;
  eta_minutes?: number | null;
  pin_required?: boolean;
  pin_verified?: boolean;
  payment_method?: string;
  card_machine_due?: number | null;
  no_show_wait_minutes?: number;
  arrived_at?: string | null;
  can?: { accept: boolean; en_route: boolean; arrived: boolean; start: boolean; complete: boolean; no_show: boolean; chat: boolean; call: boolean };
};

export type Offer = {
  id: number;
  role: string;
  service: string;
  pickup: string;
  pickup_lat: number;
  pickup_lng: number;
  dropoff: string | null;
  distance_km: number | null;
  duration_minutes: number;
  requirements: string[];
  expires_at: string;
  seconds_left: number;
  payout_estimate: number | null;
};

export type CrewMe = {
  operator: {
    id: number;
    name: string;
    first_name: string;
    call_sign: string;
    phone: string;
    photo_url: string | null;
    employment_type: string;
    roles: string[];
    status: string;
    is_online: boolean;
    psira_number: string | null;
    psira_grade: string | null;
    rating: number | null;
    vehicle: { id: number; label: string; class: string } | null;
    features: { name: string; active: boolean }[];
    can_work: boolean;
    issues: string[];
    safety_pin_set: boolean;
  };
  earnings: Record<string, any> | null;
  control_room_phone: string;
};
