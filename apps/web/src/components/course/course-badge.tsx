import { cn } from "@/lib/utils";
import type { Course } from "@/lib/demo-data";

const styles: Record<NonNullable<Course["badge"]>, string> = {
  Bestseller: "bg-gold-50 text-gold-600 ring-gold-200",
  "Highest rated": "bg-navy-100 text-navy-800 ring-navy-100",
  New: "bg-[#e7f1ec] text-success ring-[#cfe3d8]",
};

export function CourseBadge({ badge, className }: { badge: NonNullable<Course["badge"]>; className?: string }) {
  return (
    <span
      className={cn(
        "inline-flex h-6 items-center rounded-sm px-2 text-[0.7rem] font-bold tracking-wide ring-1 ring-inset",
        styles[badge],
        className,
      )}
    >
      {badge}
    </span>
  );
}
