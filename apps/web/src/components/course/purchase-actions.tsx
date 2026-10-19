"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { Heart } from "lucide-react";
import { FormAlert } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiRequestError, mutate } from "@/lib/api/client";
import { useMe } from "@/lib/auth/session";
import { useCart, useCartActions, useWishlist, useWishlistToggle } from "@/lib/commerce/api";
import { myLearningKey, useEnrollmentStatus } from "@/lib/learning/api";
import { useHydrated } from "@/lib/use-hydrated";
import { cn } from "@/lib/utils";

/**
 * The course page's call to action. Learners who already have access go straight to the
 * course; free courses enroll in one click; paid courses go through the cart.
 */
export function PurchaseActions({ slug, free }: { slug: string; free: boolean }) {
  const hydrated = useHydrated();
  const { data: me, isPending: mePending } = useMe();
  const status = useEnrollmentStatus(slug, !!me);
  const router = useRouter();
  const queryClient = useQueryClient();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const cart = useCart();
  const { add } = useCartActions();
  const wishlist = useWishlist(!!me);
  const toggleWish = useWishlistToggle();

  if (!hydrated || mePending || (me && status.isPending)) return <Skeleton className="h-12 w-full" />;

  const role = status.data?.role;
  if (role) {
    const label = role === "learner" ? (status.data!.progress_percent > 0 ? "Continue learning" : "Go to course") : "Preview course";
    return (
      <div className="space-y-2.5">
        <Link href={`/learn/${slug}`} className={buttonVariants({ size: "lg", className: "w-full" })}>
          {label}
        </Link>
        {role === "learner" && <p className="text-center text-xs text-stone-500">You&rsquo;re enrolled in this course.</p>}
      </div>
    );
  }

  const inCart = !!cart.data?.items.some((i) => i.course.slug === slug);
  const saved = !!wishlist.data?.some((c) => c.slug === slug);

  /** Add, then optionally go straight on (Buy now → checkout, which asks guests to sign in). */
  const addToCart = async (then?: string) => {
    setError(null);
    if (!inCart) {
      setBusy(true);
      try {
        await add.mutateAsync(slug);
      } catch (e) {
        setError(e instanceof ApiRequestError ? e.message : "Something went wrong. Please try again.");
        setBusy(false);
        return;
      }
      setBusy(false);
    }
    if (then) router.push(me ? then : `/login?next=${encodeURIComponent(then)}`);
  };

  const enroll = async () => {
    if (!me) {
      router.push(`/login?next=${encodeURIComponent(`/course/${slug}`)}`);
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await mutate(() => api.POST("/courses/{slug}/enroll", { params: { path: { slug } } }));
      void queryClient.invalidateQueries({ queryKey: myLearningKey });
      router.push(`/learn/${slug}`);
    } catch (e) {
      setError(e instanceof ApiRequestError ? e.message : "Something went wrong. Please try again.");
      setBusy(false);
    }
  };

  return (
    <div className="space-y-2.5">
      {error && <FormAlert>{error}</FormAlert>}
      {free ? (
        <Button size="lg" className="w-full" onClick={enroll} disabled={busy}>
          {busy ? "Enrolling…" : "Enroll for free"}
        </Button>
      ) : (
        <>
          <div className="flex gap-2">
            {inCart ? (
              <Link href="/cart" className={buttonVariants({ size: "lg", className: "flex-1" })}>
                Go to cart
              </Link>
            ) : (
              <Button size="lg" className="flex-1" onClick={() => addToCart()} disabled={busy}>
                {busy ? "Adding…" : "Add to cart"}
              </Button>
            )}
            {me && (
              <Button
                size="lg"
                variant="outline"
                aria-label={saved ? "Remove from wishlist" : "Add to wishlist"}
                aria-pressed={saved}
                onClick={() => toggleWish.mutate({ slug, saved })}
                disabled={toggleWish.isPending}
                className="px-3"
              >
                <Heart className={cn("size-5", saved && "fill-current text-destructive")} />
              </Button>
            )}
          </div>
          <Button size="lg" variant="outline" className="w-full" onClick={() => addToCart("/checkout")} disabled={busy}>
            Buy now
          </Button>
        </>
      )}
    </div>
  );
}
