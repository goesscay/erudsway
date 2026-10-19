import { badgeLabel } from "@/lib/catalog/types";
import { cn } from "@/lib/utils";

const styles: Record<string, string> = {
  bestseller: "bg-gold-50 text-gold-600 ring-gold-200",
  highest_rated: "bg-navy-100 text-navy-800 ring-navy-100",
  new: "bg-[#e7f1ec] text-success ring-[#cfe3d8]",
};

export function CourseBadge({ badge, className }: { badge: string; className?: string }) {
  return (
    <span
      className={cn(
        "inline-flex h-6 items-center rounded-sm px-2 text-[0.7rem] font-bold tracking-wide ring-1 ring-inset",
        styles[badge] ?? "bg-paper-2 text-stone-700 ring-line",
        className,
      )}
    >
      {badgeLabel[badge] ?? badge}
    </span>
  );
}
