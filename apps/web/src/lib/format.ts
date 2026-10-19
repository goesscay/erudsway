const inr = new Intl.NumberFormat("en-IN", {
  style: "currency",
  currency: "INR",
  maximumFractionDigits: 0,
});

const inrExact = new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", minimumFractionDigits: 2, maximumFractionDigits: 2 });

/**
 * Amounts travel as integer paise from the API; never format floats. Prices show whole
 * rupees; pass `exact` for money statements (earnings, TDS, payouts) where paise matter.
 */
export function formatPaise(paise: number, exact = false): string {
  return (exact ? inrExact : inr).format(paise / 100);
}

export function formatCount(n: number): string {
  return new Intl.NumberFormat("en-IN", { notation: n >= 100000 ? "compact" : "standard" }).format(n);
}

/** "1 lecture", "12 lectures". */
export function plural(n: number, word: string): string {
  return `${formatCount(n)} ${word}${n === 1 ? "" : "s"}`;
}

export function formatDuration(seconds: number): string {
  const h = Math.floor(seconds / 3600);
  const m = Math.round((seconds % 3600) / 60);
  if (h === 0) return `${m} min`;
  return m === 0 ? `${h} hr` : `${h} hr ${m} min`;
}

export function formatClock(seconds: number): string {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${m}:${String(s).padStart(2, "0")}`;
}

/** Professional prefixes common in Indian practice; skipped when building initials. */
const honorifics = new Set(["ca", "cs", "cma", "adv", "adv.", "dr", "dr.", "advocate"]);

export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter((part) => part && !honorifics.has(part.toLowerCase()))
    .slice(0, 2)
    .map((p) => p[0]!.toUpperCase())
    .join("");
}
