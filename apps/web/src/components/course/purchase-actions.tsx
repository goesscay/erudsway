"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";

/**
 * Cart and checkout arrive in Phase 5. Until then the buttons explain that instead of
 * pretending to work.
 */
export function PurchaseActions({ free }: { free: boolean }) {
  const [notice, setNotice] = useState(false);

  return (
    <div className="space-y-2.5">
      {free ? (
        <Button size="lg" className="w-full" onClick={() => setNotice(true)}>
          Enrol for free
        </Button>
      ) : (
        <>
          <Button size="lg" className="w-full" onClick={() => setNotice(true)}>
            Add to cart
          </Button>
          <Button size="lg" variant="outline" className="w-full" onClick={() => setNotice(true)}>
            Buy now
          </Button>
        </>
      )}
      {notice && (
        <p role="status" className="rounded-md bg-navy-100/60 px-3 py-2 text-center text-xs text-navy-800">
          Enrolment opens soon — we&rsquo;re finishing secure checkout.
        </p>
      )}
    </div>
  );
}
