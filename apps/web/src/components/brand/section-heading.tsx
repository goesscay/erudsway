import { cn } from "@/lib/utils";

export function SectionHeading({
  eyebrow,
  title,
  children,
  className,
}: {
  eyebrow?: string;
  title: string;
  children?: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("max-w-2xl", className)}>
      {eyebrow && (
        <div className="mb-3 flex items-center gap-3">
          <span className="rule-gold" aria-hidden />
          <span className="text-xs font-bold tracking-[0.18em] text-gold-600 uppercase">{eyebrow}</span>
        </div>
      )}
      <h2 className="text-h2">{title}</h2>
      {children && <p className="mt-3 text-[0.95rem] leading-relaxed text-stone-700">{children}</p>}
    </div>
  );
}
