"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { FormAlert } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiRequestError, mutate } from "@/lib/api/client";
import { useMe } from "@/lib/auth/session";
import { myLearningKey, useEnrollmentStatus } from "@/lib/learning/api";
import { useHydrated } from "@/lib/use-hydrated";

/**
 * The course page's call to action. Learners who already have access go straight to the
 * course; free courses enroll in one click. Paid checkout arrives in Phase 5.
 */
export function PurchaseActions({ slug, free }: { slug: string; free: boolean }) {
  const hydrated = useHydrated();
  const { data: me, isPending: mePending } = useMe();
  const status = useEnrollmentStatus(slug, !!me);
  const router = useRouter();
  const queryClient = useQueryClient();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState(false);

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
          Checkout opens soon — we&rsquo;re finishing secure payments.
        </p>
      )}
    </div>
  );
}
