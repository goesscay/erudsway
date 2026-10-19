"use client";

import { useState } from "react";
import type { Range } from "@/lib/admin/api";

/** Today's date in India as YYYY-MM-DD (reports are read in IST). */
export function istToday(): string {
  return new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Kolkata" }).format(new Date());
}

function shift(date: string, days: number): string {
  const d = new Date(`${date}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

export function presetRange(preset: string, today = istToday()): Range {
  const [y, m] = today.split("-").map(Number) as [number, number];
  switch (preset) {
    case "7d":
      return { from: shift(today, -6), to: today };
    case "90d":
      return { from: shift(today, -89), to: today };
    case "month":
      return { from: `${today.slice(0, 7)}-01`, to: today };
    case "last-month": {
      const first = new Date(Date.UTC(m === 1 ? y - 1 : y, m === 1 ? 11 : m - 2, 1));
      const last = new Date(Date.UTC(y, m - 1, 0));
      return { from: first.toISOString().slice(0, 10), to: last.toISOString().slice(0, 10) };
    }
    case "fy": {
      // Indian financial year: 1 April to 31 March.
      const start = m >= 4 ? y : y - 1;
      return { from: `${start}-04-01`, to: today };
    }
    default:
      return { from: shift(today, -29), to: today };
  }
}

const presets = [
  ["7d", "Last 7 days"],
  ["30d", "Last 30 days"],
  ["90d", "Last 90 days"],
  ["month", "This month"],
  ["last-month", "Last month"],
  ["fy", "This financial year"],
  ["custom", "Custom…"],
] as const;

/** Preset periods plus a custom from/to. */
export function DateRangePicker({ value, onChange }: { value: Range; onChange: (r: Range) => void }) {
  const [preset, setPreset] = useState("30d");

  return (
    <div className="flex flex-wrap items-center gap-2 text-sm">
      <select
        aria-label="Period"
        value={preset}
        onChange={(e) => {
          setPreset(e.target.value);
          if (e.target.value !== "custom") onChange(presetRange(e.target.value));
        }}
        className="h-9 rounded-md border border-input bg-white px-2.5 font-semibold"
      >
        {presets.map(([k, label]) => (
          <option key={k} value={k}>
            {label}
          </option>
        ))}
      </select>
      {preset === "custom" && (
        <>
          <input
            type="date"
            aria-label="From"
            value={value.from}
            max={value.to}
            onChange={(e) => e.target.value && onChange({ ...value, from: e.target.value })}
            className="h-9 rounded-md border border-input bg-white px-2"
          />
          <span className="text-stone-500">to</span>
          <input
            type="date"
            aria-label="To"
            value={value.to}
            min={value.from}
            onChange={(e) => e.target.value && onChange({ ...value, to: e.target.value })}
            className="h-9 rounded-md border border-input bg-white px-2"
          />
        </>
      )}
    </div>
  );
}
