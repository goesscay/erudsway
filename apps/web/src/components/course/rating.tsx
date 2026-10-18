import { cn } from "@/lib/utils";
import { formatCount } from "@/lib/format";

function Star({ fill }: { fill: number }) {
  // fill: 0..1 — partial stars rendered with a clip, not rounded to halves.
  const id = `s${Math.round(fill * 100)}`;
  return (
    <svg viewBox="0 0 20 20" className="size-3.5" aria-hidden>
      <defs>
        <linearGradient id={id}>
          <stop offset={`${fill * 100}%`} stopColor="var(--gold-500)" />
          <stop offset={`${fill * 100}%`} stopColor="transparent" />
        </linearGradient>
      </defs>
      <path
        d="M10 1.6l2.5 5.3 5.8.7-4.3 4 1.1 5.7L10 14.5l-5.1 2.8 1.1-5.7-4.3-4 5.8-.7z"
        fill={`url(#${id})`}
        stroke="var(--gold-500)"
        strokeWidth="1.1"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export function Rating({
  value,
  count,
  className,
  size = "sm",
}: {
  value: number;
  count?: number;
  className?: string;
  size?: "sm" | "md";
}) {
  return (
    <div className={cn("flex items-center gap-1.5", className)}>
      <span className={cn("tabular font-bold text-gold-600", size === "md" ? "text-base" : "text-sm")}>
        {value.toFixed(1)}
      </span>
      <span className="flex gap-px" role="img" aria-label={`Rated ${value} out of 5`}>
        {[0, 1, 2, 3, 4].map((i) => (
          <Star key={i} fill={Math.max(0, Math.min(1, value - i))} />
        ))}
      </span>
      {count !== undefined && (
        <span className="tabular text-xs text-muted-foreground">({formatCount(count)})</span>
      )}
    </div>
  );
}
