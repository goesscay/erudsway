"use client";

import Link from "next/link";
import { ShoppingCart } from "lucide-react";
import { buttonVariants } from "@/components/ui/button";
import { useCart } from "@/lib/commerce/api";
import { useHydrated } from "@/lib/use-hydrated";
import { cn } from "@/lib/utils";

export function CartLink() {
  const hydrated = useHydrated();
  const { data } = useCart();
  const count = hydrated ? (data?.count ?? 0) : 0;

  return (
    <Link
      href="/cart"
      aria-label={count ? `Cart, ${count} ${count === 1 ? "course" : "courses"}` : "Cart"}
      className={cn(buttonVariants({ variant: "ghost", size: "icon" }), "relative text-stone-700")}
    >
      <ShoppingCart className="size-5" />
      {count > 0 && (
        <span className="tabular absolute -top-0.5 -right-0.5 flex size-[1.15rem] items-center justify-center rounded-full bg-gold-500 text-[0.65rem] font-bold text-navy-950">
          {count}
        </span>
      )}
    </Link>
  );
}
