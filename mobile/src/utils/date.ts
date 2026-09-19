const RU_MONTHS = [
  'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
  'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря',
];

// Бэкенд отдаёт "YYYY-MM-DD HH:MM:SS" в московском времени без смещения --
// парсим вручную, чтобы new Date() не трактовал строку как UTC.
function parseServerDate(datetime: string): Date {
  const [datePart, timePart] = datetime.split(' ');
  const [y, m, d] = datePart.split('-').map(Number);
  const [h, min, s] = (timePart ?? '00:00:00').split(':').map(Number);
  return new Date(y, m - 1, d, h, min, s ?? 0);
}

export function ruDate(datetime: string | null | undefined, withTime = false): string {
  if (!datetime) return '—';
  const d = parseServerDate(datetime);
  const base = `${d.getDate()} ${RU_MONTHS[d.getMonth()]} ${d.getFullYear()}`;
  if (!withTime) return base;
  const hh = String(d.getHours()).padStart(2, '0');
  const mm = String(d.getMinutes()).padStart(2, '0');
  return `${base}, ${hh}:${mm}`;
}

export function ruDay(datetime: string): { day: string; month: string } {
  const d = parseServerDate(datetime);
  return { day: String(d.getDate()), month: RU_MONTHS[d.getMonth()].slice(0, 3).toUpperCase() };
}

export function plural(n: number, one: string, few: string, many: string): string {
  const mod10 = n % 10;
  const mod100 = n % 100;
  if (mod10 === 1 && mod100 !== 11) return one;
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return few;
  return many;
}
