import type { Metadata } from "next";
import { Suspense } from "react";
import { OrderComplete } from "./order-complete";

export const metadata: Metadata = { title: "Order confirmed", robots: { index: false } };

export default function OrderCompletePage() {
  return (
    <Suspense fallback={<div className="mx-auto min-h-[60vh] max-w-2xl px-4 py-16" />}>
      <OrderComplete />
    </Suspense>
  );
}
