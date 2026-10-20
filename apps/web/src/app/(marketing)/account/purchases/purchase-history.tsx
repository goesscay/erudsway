"use client";

import Link from "next/link";
import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { Download, Receipt } from "lucide-react";
import { formatDate } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate } from "@/lib/api/client";
import { ordersKey, useOrders, type Order, type OrderItem } from "@/lib/commerce/api";
import { formatPaise } from "@/lib/format";
import { myLearningKey } from "@/lib/learning/api";

const refundLabel: Record<NonNullable<OrderItem["refund"]>["status"], string> = {
  requested: "Refund requested — under review",
  processing: "Refund issued — on its way to you",
  processed: "Refunded",
  rejected: "Refund declined",
  failed: "Refund delayed — our team is on it",
};

export function PurchaseHistory() {
  return (
    <RequireAuth>
      {() => (
        <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
          <h1 className="text-h1">Purchase history</h1>
          <Orders />
        </div>
      )}
    </RequireAuth>
  );
}

function Orders() {
  const orders = useOrders();
  const [refundFor, setRefundFor] = useState<{ order: Order; item: OrderItem } | null>(null);

  if (orders.isPending) return <Skeleton className="mt-8 h-48 w-full" />;
  if (orders.isError)
    return (
      <div className="mt-8">
        <FormAlert>We couldn&rsquo;t load your purchases.</FormAlert>
      </div>
    );
  if (orders.data.length === 0) {
    return (
      <div className="mt-8 rounded-md border border-line bg-white px-6 py-16 text-center">
        <Receipt className="mx-auto size-10 text-gold-600" strokeWidth={1.5} />
        <p className="mt-4 font-semibold text-ink">No purchases yet</p>
        <Link href="/courses" className={buttonVariants({ className: "mt-6" })}>
          Browse courses
        </Link>
      </div>
    );
  }

  return (
    <>
      <ul className="mt-8 space-y-5">
        {orders.data.map((order) => (
          <li key={order.id} className="rounded-md border border-line bg-white">
            <div className="flex flex-wrap items-center gap-x-6 gap-y-1 border-b border-line bg-paper-2/50 px-5 py-3 text-xs text-stone-700">
              <span>
                <span className="text-stone-500">Ordered</span> {formatDate(order.paid_at)}
              </span>
              <span>
                <span className="text-stone-500">Total</span>{" "}
                <strong className="tabular">{formatPaise(order.total)}</strong>
              </span>
              <span>
                <span className="text-stone-500">Order</span> {order.number}
              </span>
              {order.invoice && (
                <a
                  href={order.invoice.download_url}
                  className="ml-auto inline-flex items-center gap-1 font-semibold text-navy-800 hover:underline"
                >
                  <Download className="size-3.5" /> Tax invoice
                </a>
              )}
            </div>
            <ul className="divide-y divide-line">
              {order.items.map((item) => (
                <li key={item.id} className="flex flex-wrap items-center gap-4 px-5 py-4">
                  <div className="min-w-0 flex-1">
                    <Link href={`/course/${item.course.slug}`} className="font-semibold text-ink hover:text-navy-800">
                      {item.course.title}
                    </Link>
                    <p className="tabular mt-0.5 text-xs text-stone-500">
                      {formatPaise(item.amount)}
                      {item.discount > 0 && <> (saved {formatPaise(item.discount)})</>}
                    </p>
                    {item.refund && (
                      <p className="mt-1 text-xs font-semibold text-stone-700">
                        {refundLabel[item.refund.status]}
                        {item.refund.status === "rejected" && item.refund.decision_note && (
                          <span className="font-normal text-stone-500"> — {item.refund.decision_note}</span>
                        )}
                      </p>
                    )}
                  </div>
                  {item.refundable && (
                    <Button size="sm" variant="ghost" onClick={() => setRefundFor({ order, item })}>
                      Request a refund
                    </Button>
                  )}
                </li>
              ))}
            </ul>
          </li>
        ))}
      </ul>
      <p className="mt-6 text-xs text-stone-500">
        Course purchases are final. Charged twice or can&rsquo;t access a course? Email{" "}
        <a href="mailto:support@erudsway.com" className="underline underline-offset-2">
          support@erudsway.com
        </a>{" "}
        with your order number — see the{" "}
        <Link href="/policies#refund" className="underline underline-offset-2">
          Refund &amp; Cancellation policy
        </Link>
        .
      </p>
      <RefundDialog target={refundFor} onClose={() => setRefundFor(null)} />
    </>
  );
}

function RefundDialog({ target, onClose }: { target: { order: Order; item: OrderItem } | null; onClose: () => void }) {
  const queryClient = useQueryClient();
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (!target) return;
    setBusy(true);
    setError(null);
    try {
      await mutate(() =>
        api.POST("/order-items/{item}/refund-request", {
          params: { path: { item: target.item.id } },
          body: { reason: reason.trim() },
        }),
      );
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ordersKey }),
        queryClient.invalidateQueries({ queryKey: myLearningKey }),
      ]);
      setReason("");
      onClose();
    } catch (e) {
      setError(e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={!!target} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Request a refund</DialogTitle>
          <DialogDescription>
            {target?.item.course.title} · {target && formatPaise(target.item.amount)}. If you&rsquo;ve completed less
            than 30% of the course, the refund is issued straight away and your access ends. Otherwise our team reviews
            it within two working days.
          </DialogDescription>
        </DialogHeader>
        {error && <FormAlert>{error}</FormAlert>}
        <div className="space-y-1.5">
          <Label htmlFor="refund-reason" className="text-sm font-semibold text-ink">
            What went wrong?
          </Label>
          <Textarea
            id="refund-reason"
            rows={4}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="Helps us improve the course."
            className="bg-white"
          />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Keep the course
          </Button>
          <Button variant="destructive" onClick={submit} disabled={reason.trim().length < 10 || busy}>
            {busy ? "Sending…" : "Request refund"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
