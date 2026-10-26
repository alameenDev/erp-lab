// Calendar dates only: no UTC/local-time shifts or 365-day approximations.
export const todayInBaghdad = () => new Intl.DateTimeFormat('en-CA', {
  timeZone: 'Asia/Baghdad', year: 'numeric', month: '2-digit', day: '2-digit',
}).format(new Date());

export function calendarDate(value) {
  const m = String(value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})(?:T.*)?$/);
  if (!m) return null;
  const [year, month, day] = m.slice(1).map(Number);
  const date = new Date(0);
  date.setUTCFullYear(year, month - 1, day); date.setUTCHours(0, 0, 0, 0);
  return year >= 1 && year <= 9999 && date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day ? date : null;
}
const iso = date => date.toISOString().slice(0, 10);
function moveMonths(date, amount) {
  const moved = new Date(date);
  const day = moved.getUTCDate(); moved.setUTCDate(1);
  moved.setUTCMonth(moved.getUTCMonth() + amount);
  const end = new Date(moved); end.setUTCMonth(end.getUTCMonth() + 1); end.setUTCDate(0);
  moved.setUTCDate(Math.min(day, end.getUTCDate()));
  return moved;
}
export function ageFromBirthDate(dob, today = todayInBaghdad()) {
  const birth = calendarDate(dob), now = calendarDate(today);
  if (!birth || !now || birth > now) return null;
  let months = (now.getUTCFullYear() - birth.getUTCFullYear()) * 12 + now.getUTCMonth() - birth.getUTCMonth();
  if (moveMonths(birth, months) > now) months--;
  if (months >= 12) return { age: Math.floor(months / 12), unit: 'Years' };
  if (months >= 1) return { age: months, unit: 'Months' };
  return { age: Math.round((now - birth) / 86400000), unit: 'Days' };
}
export function birthDateFromAge(age, unit, today = todayInBaghdad()) {
  if (age === '' || age === null || age === undefined || !Number.isSafeInteger(Number(age)) || Number(age) < 0) return null;
  const now = calendarDate(today);
  if (!now) return null;
  let birth;
  if (unit === 'Years' || unit === 'Months') birth = moveMonths(now, -Number(age) * (unit === 'Years' ? 12 : 1));
  else if (unit === 'Days') { birth = new Date(now); birth.setUTCDate(birth.getUTCDate() - Number(age)); }
  else return null;
  return Number.isFinite(birth.getTime()) && birth.getUTCFullYear() >= 1 && birth.getUTCFullYear() <= 9999 ? iso(birth) : null;
}
export function birthDateFromParts({ day, month, year }) {
  if (![day, month, year].every(v => /^\d+$/.test(String(v)))) return null;
  const value = `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
  return calendarDate(value) ? value : null;
}
export const isEstimatedBirthDate = value => value === true || value === 1 || value === '1';
