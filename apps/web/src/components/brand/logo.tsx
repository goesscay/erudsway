import Image from "next/image";
import Link from "next/link";
import { cn } from "@/lib/utils";

/** Compact horizontal lockup for headers: the S-mark plus a typeset wordmark (or the mark alone). */
export function Logo({
  className,
  inverted = false,
  markOnly = false,
}: {
  className?: string;
  inverted?: boolean;
  markOnly?: boolean;
}) {
  return (
    <Link href="/" className={cn("inline-flex items-center gap-2.5", className)} aria-label="Erudsway home">
      <Image
        src="/brand/erudsway-mark.png"
        alt=""
        width={173}
        height={158}
        preload
        className={cn("h-9 w-auto", inverted && "brightness-0 invert")}
      />
      <span className={cn("flex flex-col leading-none", markOnly && "hidden")}>
        <span
          className={cn(
            "font-sans text-[1.15rem] font-extrabold tracking-[0.14em]",
            inverted ? "text-white" : "text-navy-900",
          )}
        >
          ERUDS<span className={inverted ? "text-gold-400" : "text-gold-500"}>WAY</span>
        </span>
        <span
          className={cn(
            "mt-1 text-[0.58rem] font-semibold tracking-[0.32em] uppercase",
            inverted ? "text-white/55" : "text-stone-500",
          )}
        >
          Educorp LLP
        </span>
      </span>
    </Link>
  );
}
