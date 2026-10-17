// Plain formatting helpers (no Intl dependency, so output is identical on every phone).

export function rands(amount: number | null | undefined, cents = false): string {
  if (amount === null || amount === undefined || isNaN(Number(amount))) return 'R—';
  const n = Number(amount);
  const fixed = Math.abs(n).toFixed(cents ? 2 : 0);
  const [whole, dec] = fixed.split('.');
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  return `${n < 0 ? '-' : ''}R${grouped}${dec ? `.${dec}` : ''}`;
}

const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const DAYS_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const MONTHS_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const pad = (n: number) => String(n).padStart(2, '0');
// Every time is shown in Cape Town time (UTC+2, no daylight saving), whatever timezone the phone is set to.
const SA_OFFSET_MIN = 120;
const toDate = (d: string | Date) => {
  const x = typeof d === 'string' ? new Date(d) : d;
  return new Date(x.getTime() + (SA_OFFSET_MIN + x.getTimezoneOffset()) * 60000);
};
/** "Now" as a Cape Town wall-clock Date (use only for display maths and picker building). */
export const saNow = () => toDate(new Date());

export const fmt = {
  time: (d: string | Date) => {
    const x = toDate(d);
    return `${pad(x.getHours())}:${pad(x.getMinutes())}`;
  },
  dayShort: (d: string | Date) => {
    const x = toDate(d);
    return `${DAYS[x.getDay()]} ${x.getDate()} ${MONTHS[x.getMonth()]}`;
  },
  dayLong: (d: string | Date) => {
    const x = toDate(d);
    return `${DAYS_LONG[x.getDay()]} ${x.getDate()} ${MONTHS_LONG[x.getMonth()]}`;
  },
  date: (d: string | Date) => {
    const x = toDate(d);
    return `${x.getDate()} ${MONTHS[x.getMonth()]} ${x.getFullYear()}`;
  },
  dateTime: (d: string | Date) => `${fmt.dayShort(d)}, ${fmt.time(d)}`,
  relativeDay: (d: string | Date) => {
    const x = toDate(d);
    const today = saNow();
    const start = (y: Date) => new Date(y.getFullYear(), y.getMonth(), y.getDate()).getTime();
    const diff = Math.round((start(x) - start(today)) / 86400000);
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Tomorrow';
    if (diff === -1) return 'Yesterday';
    return fmt.dayShort(x);
  },
  minutes: (m: number) => {
    const h = Math.floor(m / 60);
    const r = m % 60;
    return [h ? `${h} hour${h === 1 ? '' : 's'}` : '', r ? `${r} min` : ''].filter(Boolean).join(' ') || '0 min';
  },
  /** A Cape Town wall-clock Date (from saNow or the picker) as an ISO string with the +02:00 offset. */
  isoSA: (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}:00+02:00`,
  ymd: (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
};

export function greeting(name?: string | null) {
  const h = new Date().getHours();
  const part = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
  return name ? `${part}, ${name}` : part;
}

export function displayPhone(e164?: string | null) {
  if (!e164) return '';
  const m = e164.match(/^\+27(\d{2})(\d{3})(\d{4})$/);
  return m ? `0${m[1]} ${m[2]} ${m[3]}` : e164;
}
