import { initials } from "@/lib/format";
import { cn } from "@/lib/utils";

/** Monogram avatar used until an instructor uploads a photo. */
export function InstructorAvatar({ name, className }: { name: string; className?: string }) {
  return (
    <span
      aria-hidden
      className={cn(
        "inline-flex shrink-0 items-center justify-center rounded-full bg-navy-900 font-serif font-semibold text-gold-200",
        className ?? "size-12 text-base",
      )}
    >
      {initials(name)}
    </span>
  );
}
