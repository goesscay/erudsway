"use client";

import Link from "next/link";
import { LifeBuoy, ListChecks, LogIn, Mail } from "lucide-react";
import { buttonVariants } from "@/components/ui/button";
import { useMe } from "@/lib/auth/session";
import { useHydrated } from "@/lib/use-hydrated";

/** Signed-in learners raise or follow tickets; visitors sign in first or use the Contact page. */
export function SupportActions() {
  const hydrated = useHydrated();
  const { data: me } = useMe();

  if (hydrated && me) {
    return (
      <div className="flex flex-wrap gap-3">
        <Link href="/help/tickets/new" className={buttonVariants({ size: "lg" })}>
          <LifeBuoy /> Raise a ticket
        </Link>
        <Link href="/help/tickets" className={buttonVariants({ size: "lg", variant: "outline" })}>
          <ListChecks /> My tickets
        </Link>
      </div>
    );
  }

  return (
    <div className="flex flex-wrap gap-3">
      <Link href="/login?next=%2Fhelp%2Ftickets%2Fnew" className={buttonVariants({ size: "lg" })}>
        <LogIn /> Sign in to raise a ticket
      </Link>
      <Link href="/contact" className={buttonVariants({ size: "lg", variant: "outline" })}>
        <Mail /> Contact us without an account
      </Link>
    </div>
  );
}
