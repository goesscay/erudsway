"use client";

import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useQueryClient } from "@tanstack/react-query";
import { Lock, ShieldCheck } from "lucide-react";
import { RequireAuth } from "@/components/auth/require-auth";
import { Field, FormAlert, TextInput } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiRequestError, mutate } from "@/lib/api/client";
import { applyApiErrors } from "@/lib/api/form-errors";
import type { CurrentUser } from "@/lib/auth/session";
import { cartKey, ordersKey, useCart, useGstStates, type Cart } from "@/lib/commerce/api";
import { payWithRazorpay } from "@/lib/commerce/razorpay";
import { formatPaise } from "@/lib/format";
import { myLearningKey } from "@/lib/learning/api";
import { billingPayload, billingSchema, type BillingValues } from "@/lib/validation/checkout";

type Started = { orderId: string; gatewayOrderId: string; fakeSignature: string | null };

export function CheckoutView() {
  return <RequireAuth>{(me) => <Checkout me={me} />}</RequireAuth>;
}

function Checkout({ me }: { me: CurrentUser }) {
  const cart = useCart();

  if (cart.isPending) {
    return (
      <Shell>
        <div className="grid gap-10 lg:grid-cols-[1fr_360px]">
          <Skeleton className="h-80 w-full" />
          <Skeleton className="h-64 w-full" />
        </div>
      </Shell>
    );
  }
  if (cart.isError || cart.data.count === 0) {
    return (
      <Shell>
        <p className="text-stone-700">Your cart is empty.</p>
        <Link href="/courses" className={buttonVariants({ className: "mt-4" })}>
          Browse courses
        </Link>
      </Shell>
    );
  }

  return (
    <Shell>
      {!me.email_verified && (
        <div className="mb-6">
          <FormAlert tone="info">
            Verify your email address before paying — we send your receipt and tax invoice there.{" "}
            <Link href="/verify-email?next=/checkout" className="font-semibold underline">
              Verify now
            </Link>
          </FormAlert>
        </div>
      )}
      <div className="grid items-start gap-10 lg:grid-cols-[1fr_360px]">
        <BillingForm me={me} cart={cart.data} />
        <Summary cart={cart.data} />
      </div>
    </Shell>
  );
}

function Shell({ children }: { children: React.ReactNode }) {
  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
      <h1 className="text-h1 mb-8">Checkout</h1>
      {children}
    </div>
  );
}

function BillingForm({ me, cart }: { me: CurrentUser; cart: Cart }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const states = useGstStates();
  const [formError, setFormError] = useState<string | null>(null);
  const [fake, setFake] = useState<Started | null>(null);
  const [verifying, setVerifying] = useState(false);

  const form = useForm<BillingValues>({
    resolver: zodResolver(billingSchema),
    defaultValues: { name: me.name, state_code: "", business: false, gstin: "", business_name: "", address: "" },
  });
  const { errors, isSubmitting } = form.formState;
  const business = useWatch({ control: form.control, name: "business" });

  const finish = async (orderId: string) => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: cartKey }),
      queryClient.invalidateQueries({ queryKey: myLearningKey }),
      queryClient.invalidateQueries({ queryKey: ordersKey }),
    ]);
    router.replace(`/checkout/complete?order=${orderId}`);
  };

  const verify = async (orderId: string, body: { razorpay_payment_id: string; razorpay_order_id: string; razorpay_signature: string }) => {
    setVerifying(true);
    try {
      await mutate(() => api.POST("/orders/{order}/verify", { params: { path: { order: orderId } }, body }));
      await finish(orderId);
    } catch (e) {
      setVerifying(false);
      setFormError(e instanceof ApiRequestError ? e.message : "We couldn’t confirm your payment. If you were charged, you’ll get access within a few minutes.");
    }
  };

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      const { data } = await mutate(() => api.POST("/checkout", { body: billingPayload(values) }));
      if (!data.payment) {
        await finish(data.order.id); // fully discounted: nothing to pay
        return;
      }
      const started = { orderId: data.order.id, gatewayOrderId: data.payment.gateway_order_id, fakeSignature: data.payment.fake_signature };
      if (data.payment.gateway === "fake") {
        setFake(started);
        return;
      }

      const outcome = await payWithRazorpay({
        key: data.payment.key,
        order_id: data.payment.gateway_order_id,
        amount: data.payment.amount,
        currency: data.payment.currency,
        name: data.payment.name,
        description: data.payment.description,
        prefill: data.payment.prefill,
      });
      if (outcome.status === "paid") await verify(started.orderId, outcome.response);
      else if (outcome.status === "failed") setFormError(`${outcome.message} No money was taken — you can try again.`);
    } catch (e) {
      if (e instanceof ApiRequestError && (e.code === "CART_CHANGED" || e.code === "COUPON_INVALID")) {
        void queryClient.invalidateQueries({ queryKey: cartKey });
      }
      setFormError(e instanceof Error && !(e instanceof ApiRequestError) ? e.message : applyApiErrors(e, form.setError, ["name", "state_code", "gstin", "business_name", "address"]));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-6 rounded-md border border-line bg-white p-6">
      <div>
        <h2 className="font-sans text-lg font-bold text-ink">Billing details</h2>
        <p className="mt-1 text-sm text-stone-500">Printed on your GST tax invoice. Your state decides how GST is shown (CGST + SGST, or IGST).</p>
      </div>
      {formError && <FormAlert>{formError}</FormAlert>}

      <Field label="Full name" error={errors.name?.message}>
        {(p) => <TextInput {...p} autoComplete="name" {...form.register("name")} />}
      </Field>

      <Field label="State" error={errors.state_code?.message}>
        {(p) => (
          <select
            {...p}
            {...form.register("state_code")}
            className="h-11 w-full rounded-md border border-input bg-white px-3 text-sm text-ink focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/30 focus-visible:outline-none"
          >
            <option value="">Choose your state…</option>
            {(states.data ?? []).map((s) => (
              <option key={s.code} value={s.code}>
                {s.name}
              </option>
            ))}
          </select>
        )}
      </Field>

      <label className="flex items-start gap-3 text-sm text-stone-700">
        <input type="checkbox" {...form.register("business")} className="mt-0.5 size-4 accent-[var(--navy-900)]" />
        <span>
          <span className="font-semibold text-ink">I need a GST invoice for my business</span>
          <span className="block text-xs text-stone-500">Add your GSTIN to claim input tax credit.</span>
        </span>
      </label>

      {business && (
        <div className="space-y-5 rounded-md bg-paper p-4">
          <Field label="GSTIN" error={errors.gstin?.message}>
            {(p) => <TextInput {...p} maxLength={15} autoComplete="off" className="uppercase" {...form.register("gstin")} />}
          </Field>
          <Field label="Registered business name" error={errors.business_name?.message}>
            {(p) => <TextInput {...p} autoComplete="organization" {...form.register("business_name")} />}
          </Field>
          <Field label="Business address" error={errors.address?.message}>
            {(p) => <TextInput {...p} autoComplete="street-address" {...form.register("address")} />}
          </Field>
        </div>
      )}

      <Button type="submit" size="lg" className="w-full" disabled={isSubmitting || verifying || !me.email_verified}>
        <Lock /> {verifying ? "Confirming payment…" : isSubmitting ? "Opening secure payment…" : `Pay ${formatPaise(cart.totals.total)}`}
      </Button>
      <p className="flex items-center justify-center gap-1.5 text-center text-xs text-stone-500">
        <ShieldCheck className="size-3.5" /> Payments are processed securely by Razorpay. We never see your card or UPI details.
      </p>

      <FakePayment
        started={fake}
        amount={cart.totals.total}
        onCancel={() => setFake(null)}
        onPay={(s) => {
          setFake(null);
          void verify(s.orderId, { razorpay_payment_id: `pay_fake_${s.gatewayOrderId}`, razorpay_order_id: s.gatewayOrderId, razorpay_signature: s.fakeSignature ?? "" });
        }}
      />
    </form>
  );
}

/** Local development only: stands in for the Razorpay window when no keys are configured. */
function FakePayment({ started, amount, onCancel, onPay }: { started: Started | null; amount: number; onCancel: () => void; onPay: (s: Started) => void }) {
  return (
    <Dialog open={!!started} onOpenChange={(o) => !o && onCancel()}>
      <DialogContent className="sm:max-w-sm">
        <DialogHeader>
          <DialogTitle>Test payment</DialogTitle>
          <DialogDescription>Razorpay keys aren&rsquo;t configured, so this local build simulates a successful payment. No money moves.</DialogDescription>
        </DialogHeader>
        <p className="tabular text-center font-serif text-3xl font-semibold text-navy-900">{formatPaise(amount)}</p>
        <DialogFooter>
          <Button variant="outline" onClick={onCancel}>
            Cancel
          </Button>
          <Button onClick={() => started && onPay(started)}>Pay (test)</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function Summary({ cart }: { cart: Cart }) {
  return (
    <aside className="order-first space-y-5 rounded-md border border-line bg-white p-6 lg:sticky lg:top-28 lg:order-none">
      <h2 className="font-sans text-lg font-bold text-ink">Order summary</h2>
      <ul className="space-y-4">
        {cart.items.map((i) => (
          <li key={i.course.id} className="flex gap-3">
            <div className="relative aspect-video w-20 shrink-0 overflow-hidden rounded-sm bg-paper-2">
              {i.course.thumbnail_url && <Image src={i.course.thumbnail_url} alt="" fill sizes="80px" className="object-cover" />}
            </div>
            <p className="line-clamp-2 flex-1 text-sm font-semibold text-ink">{i.course.title}</p>
            <p className="tabular text-sm font-semibold text-ink">{formatPaise(i.amount)}</p>
          </li>
        ))}
      </ul>
      <dl className="space-y-2 border-t border-line pt-4 text-sm">
        <Row label="Original price" value={formatPaise(cart.totals.subtotal)} />
        {cart.totals.discount > 0 && <Row label={`Discount (${cart.coupon.code})`} value={`−${formatPaise(cart.totals.discount)}`} />}
        <Row label="Included GST" value={formatPaise(cart.totals.tax)} muted />
        <div className="flex justify-between border-t border-line pt-3 text-base font-bold text-ink">
          <dt>Total</dt>
          <dd className="tabular">{formatPaise(cart.totals.total)}</dd>
        </div>
      </dl>
      <p className="text-xs text-stone-500">30-day refund if the course isn&rsquo;t right for you.</p>
    </aside>
  );
}

function Row({ label, value, muted }: { label: string; value: string; muted?: boolean }) {
  return (
    <div className={`flex justify-between ${muted ? "text-stone-500" : "text-stone-700"}`}>
      <dt>{label}</dt>
      <dd className="tabular">{value}</dd>
    </div>
  );
}
