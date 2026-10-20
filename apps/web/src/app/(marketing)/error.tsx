"use client";

import Link from "next/link";
import { CloudOff } from "lucide-react";
import { Button, buttonVariants } from "@/components/ui/button";

/** Shown when a catalogue page can't load (e.g. the API is briefly unavailable). */
export default function MarketingError({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <div className="mx-auto flex max-w-md flex-col items-center px-6 py-24 text-center">
      <CloudOff className="size-10 text-gold-600" strokeWidth={1.5} />
      <h1 className="mt-4 text-h3">We couldn&rsquo;t load this page</h1>
      <p className="mt-2 text-sm text-stone-700">Something went wrong on our side. Please try again in a moment.</p>
      <div className="mt-6 flex gap-3">
        <Button onClick={() => reset()}>Try again</Button>
        <Link href="/" className={buttonVariants({ variant: "outline" })}>
          Back to home
        </Link>
      </div>
    </div>
  );
}
