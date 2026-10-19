import type { Metadata } from "next";
import { Suspense } from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { InstructorQaView } from "./qa-view";

export const metadata: Metadata = { title: "Q&A" };

export default function InstructorQaPage() {
  return (
    <Suspense fallback={<Skeleton className="m-8 h-64" />}>
      <InstructorQaView />
    </Suspense>
  );
}
