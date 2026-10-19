import { formatPaise } from "@/lib/format";
import { cn } from "@/lib/utils";

/** Course price; null means free. Amount is in paise. */
export function Price({
  price,
  className,
}: {
  price: { amount: number; currency: string } | null | undefined;
  className?: string;
}) {
  return (
    <span className={cn("tabular font-bold text-ink", className)}>
      {price ? formatPaise(price.amount) : "Free"}
    </span>
  );
}
