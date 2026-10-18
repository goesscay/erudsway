import type { Metadata } from "next";
import { Suspense } from "react";
import { VerifyStatus } from "./verify-status";

export const metadata: Metadata = { title: "Verify your email", robots: { index: false } };

export default function VerifyEmailPage() {
  return (
    <Suspense>
      <VerifyStatus />
    </Suspense>
  );
}
