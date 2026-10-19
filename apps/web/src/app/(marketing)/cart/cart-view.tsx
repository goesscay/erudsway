"use client";

import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { CircleAlert, ShoppingCart, Tag, X } from "lucide-react";
import { FormAlert } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiRequestError } from "@/lib/api/client";
import { useMe } from "@/lib/auth/session";
import { useCart, useCartActions, type Cart } from "@/lib/commerce/api";
import { formatPaise, plural } from "@/lib/format";
import { useHydrated } from "@/lib/use-hydrated";

export function CartView() {
  const hydrated = useHydrated();
  const cart = useCart();

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
      <h1 className="text-h1">Shopping cart</h1>
      {!hydrated || cart.isPending ? (
        <div className="mt-8 grid gap-10 lg:grid-cols-[1fr_320px]">
          <Skeleton className="h-40 w-full" />
          <Skeleton className="h-56 w-full" />
        </div>
      ) : cart.isError ? (
        <div className="mt-8">
          <FormAlert>We couldn&rsquo;t load your cart. Refresh to try again.</FormAlert>
        </div>
      ) : cart.data.count === 0 ? (
        <Empty />
      ) : (
        <Filled cart={cart.data} />
      )}
    </div>
  );
}

function Empty() {
  return (
    <div className="mt-8 rounded-md border border-line bg-white px-6 py-16 text-center">
      <ShoppingCart className="mx-auto size-10 text-gold-600" strokeWidth={1.5} />
      <p className="mt-4 font-semibold text-ink">Your cart is empty</p>
      <p className="mt-1 text-sm text-stone-500">Find a course on GST, income tax, audit or company law and start learning today.</p>
      <Link href="/courses" className={buttonVariants({ size: "lg", className: "mt-6" })}>
        Browse courses
      </Link>
    </div>
  );
}

function Filled({ cart }: { cart: Cart }) {
  const router = useRouter();
  const { data: me } = useMe();
  const { remove } = useCartActions();
  const payable = cart.items.filter((i) => !i.problem).length;
  const blocked = cart.items.some((i) => i.problem);

  return (
    <div className="mt-8 grid items-start gap-10 lg:grid-cols-[1fr_320px]">
      <section aria-label="Courses in cart">
        <p className="mb-3 text-sm font-semibold text-stone-700">{plural(cart.count, "course")} in cart</p>
        <ul className="divide-y divide-line border-y border-line">
          {cart.items.map((item) => (
            <li key={item.course.id} className="flex gap-4 py-5">
              <Link href={`/course/${item.course.slug}`} className="relative aspect-video w-28 shrink-0 overflow-hidden rounded-sm bg-paper-2 sm:w-40">
                {item.course.thumbnail_url && <Image src={item.course.thumbnail_url} alt="" fill sizes="160px" className="object-cover" />}
              </Link>
              <div className="min-w-0 flex-1">
                <Link href={`/course/${item.course.slug}`} className="line-clamp-2 font-sans text-[0.95rem] leading-snug font-bold text-ink hover:text-navy-800">
                  {item.course.title}
                </Link>
                <p className="mt-1 text-xs text-stone-500">By {item.course.instructor.name}</p>
                <p className="mt-1 text-xs text-stone-500">
                  {plural(item.course.lecture_count, "lecture")} · {item.course.language}
                </p>
                {item.problem_message && (
                  <p className="mt-2 flex items-center gap-1.5 text-xs font-semibold text-destructive">
                    <CircleAlert className="size-3.5" /> {item.problem_message}
                  </p>
                )}
                <button
                  type="button"
                  onClick={() => remove.mutate(item.course.slug)}
                  className="mt-2 text-xs font-semibold text-navy-800 hover:underline"
                >
                  Remove
                </button>
              </div>
              <div className="shrink-0 text-right">
                <p className="tabular font-serif text-lg font-semibold text-navy-900">{formatPaise(item.amount)}</p>
                {item.discount > 0 && <p className="tabular text-xs text-stone-500 line-through">{formatPaise(item.list_price)}</p>}
              </div>
            </li>
          ))}
        </ul>
      </section>

      <aside className="space-y-5 rounded-md border border-line bg-white p-6 lg:sticky lg:top-28">
        <div>
          <p className="text-sm font-semibold text-stone-500">Total</p>
          <p className="tabular font-serif text-4xl font-semibold text-navy-900">{formatPaise(cart.totals.total)}</p>
          {cart.totals.discount > 0 && (
            <p className="tabular mt-1 text-sm text-stone-500">
              <span className="line-through">{formatPaise(cart.totals.subtotal)}</span>{" "}
              <span className="font-semibold text-success">{Math.round((cart.totals.discount / cart.totals.subtotal) * 100)}% off</span>
            </p>
          )}
          <p className="mt-1 text-xs text-stone-500">Includes GST of {formatPaise(cart.totals.tax)}</p>
        </div>
        {blocked && <FormAlert>Remove the courses marked above to continue.</FormAlert>}
        <Button
          size="lg"
          className="w-full"
          disabled={blocked || payable === 0}
          onClick={() => router.push(me ? "/checkout" : `/login?next=${encodeURIComponent("/checkout")}`)}
        >
          Proceed to checkout
        </Button>
        <CouponBox cart={cart} />
      </aside>
    </div>
  );
}

function CouponBox({ cart }: { cart: Cart }) {
  const { applyCoupon, removeCoupon } = useCartActions();
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);

  if (cart.coupon.code) {
    return (
      <div className="border-t border-line pt-5 text-sm">
        <div className="flex items-start justify-between gap-3">
          <p className="flex items-center gap-2">
            <Tag className="size-4 text-stone-500" />
            <span className="font-semibold text-ink">{cart.coupon.code}</span>
            {cart.coupon.applied && <span className="text-success">applied</span>}
          </p>
          <button type="button" onClick={() => removeCoupon.mutate()} aria-label="Remove coupon" className="rounded-sm p-1 text-stone-500 hover:text-navy-900">
            <X className="size-4" />
          </button>
        </div>
        {cart.coupon.message && <p className="mt-1 text-xs text-destructive">{cart.coupon.message}</p>}
      </div>
    );
  }

  return (
    <form
      className="border-t border-line pt-5"
      onSubmit={(e) => {
        e.preventDefault();
        if (!code.trim()) return;
        setError(null);
        applyCoupon.mutate(code.trim(), {
          onSuccess: () => setCode(""),
          onError: (err) => setError(err instanceof ApiRequestError ? err.message : "Couldn’t apply the code."),
        });
      }}
    >
      <label htmlFor="coupon" className="text-sm font-semibold text-ink">
        Coupon
      </label>
      <div className="mt-2 flex gap-2">
        <Input id="coupon" value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} placeholder="Enter code" autoComplete="off" className="bg-white uppercase placeholder:normal-case" />
        <Button type="submit" variant="outline" disabled={!code.trim() || applyCoupon.isPending}>
          Apply
        </Button>
      </div>
      {error && <p className="mt-1 text-xs text-destructive">{error}</p>}
    </form>
  );
}
