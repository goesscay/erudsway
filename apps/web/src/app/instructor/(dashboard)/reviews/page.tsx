import type { Metadata } from "next";
import { Suspense } from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { InstructorReviewsView } from "./reviews-view";

export const metadata: Metadata = { title: "Reviews" };

export default function InstructorReviewsPage() {
  return (
    <Suspense fallback={<Skeleton className="m-8 h-64" />}>
      <InstructorReviewsView />
    </Suspense>
  );
}
