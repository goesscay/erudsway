"use client";

import { useState } from "react";
import { Star } from "lucide-react";
import { cn } from "@/lib/utils";

const labels = [
  "",
  "Awful, not what I expected",
  "Poor, pretty disappointed",
  "Average, could be better",
  "Good, what I expected",
  "Amazing, above expectations",
];

/** Whole stars, read-only. */
export function Stars({ value, className }: { value: number; className?: string }) {
  return (
    <span className={cn("flex gap-0.5", className)} role="img" aria-label={`${value} out of 5 stars`}>
      {[1, 2, 3, 4, 5].map((i) => (
        <Star
          key={i}
          className={cn("size-3.5", i <= value ? "fill-gold-500 text-gold-500" : "text-gold-500/50")}
          strokeWidth={1.5}
        />
      ))}
    </span>
  );
}

/** Pick 1–5 stars. A radio group, so arrow keys work. */
export function StarInput({ value, onChange, id }: { value: number; onChange: (v: number) => void; id?: string }) {
  const [hover, setHover] = useState(0);
  const shown = hover || value;

  return (
    <div className="flex flex-col items-center gap-2">
      <p id={id} className="min-h-5 text-sm font-semibold text-ink" aria-live="polite">
        {shown ? labels[shown] : "Select a rating"}
      </p>
      <div role="radiogroup" aria-labelledby={id} className="flex gap-1" onMouseLeave={() => setHover(0)}>
        {[1, 2, 3, 4, 5].map((i) => (
          <button
            key={i}
            type="button"
            role="radio"
            aria-checked={value === i}
            aria-label={`${i} star${i === 1 ? "" : "s"}`}
            tabIndex={value === i || (value === 0 && i === 1) ? 0 : -1}
            onMouseEnter={() => setHover(i)}
            onClick={() => onChange(i)}
            onKeyDown={(e) => {
              if (e.key === "ArrowRight" || e.key === "ArrowUp") {
                e.preventDefault();
                onChange(Math.min(5, (value || 0) + 1));
              } else if (e.key === "ArrowLeft" || e.key === "ArrowDown") {
                e.preventDefault();
                onChange(Math.max(1, (value || 2) - 1));
              }
            }}
            className="rounded-sm p-0.5 outline-none focus-visible:ring-2 focus-visible:ring-gold-500"
          >
            <Star
              className={cn("size-9 transition-colors", i <= shown ? "fill-gold-500 text-gold-500" : "text-stone-300")}
              strokeWidth={1.25}
            />
          </button>
        ))}
      </div>
    </div>
  );
}
