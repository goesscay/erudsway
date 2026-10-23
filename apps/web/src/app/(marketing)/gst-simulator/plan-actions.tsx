"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { FormAlert } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import { ApiRequestError } from "@/lib/api/client";
import { useMe } from "@/lib/auth/session";
import { useCart, useCartActions } from "@/lib/commerce/api";

/** Buy a simulator plan: straight to checkout, or into the cart. */
export function PlanActions({ slug, edition, featured }: { slug: string; edition: number; featured: boolean }) {
  const router = useRouter();
  const { data: me } = useMe();
  const cart = useCart();
  const { add } = useCartActions();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const inCart = !!cart.data?.items.some((i) => i.course.slug === slug && i.edition?.id === edition);

  const addToCart = async (then?: string) => {
    setError(null);
    if (!inCart) {
      setBusy(true);
      try {
        await add.mutateAsync({ slug, edition });
      } catch (e) {
        setError(e instanceof ApiRequestError ? e.message : "Something went wrong. Please try again.");
        setBusy(false);
        return;
      }
      setBusy(false);
    }
    if (then) router.push(me ? then : `/login?next=${encodeURIComponent(then)}`);
  };

  return (
    <div className="space-y-2">
      {error && <FormAlert>{error}</FormAlert>}
      <Button
        size="lg"
        className="w-full"
        variant={featured ? "default" : "outline"}
        onClick={() => addToCart("/checkout")}
        disabled={busy}
      >
        {busy ? "Please wait…" : "Buy now"}
      </Button>
      {inCart ? (
        <Link href="/cart" className={buttonVariants({ variant: "ghost", className: "w-full" })}>
          In your cart — view cart
        </Link>
      ) : (
        <Button variant="ghost" className="w-full" onClick={() => addToCart()} disabled={busy}>
          Add to cart
        </Button>
      )}
    </div>
  );
}
