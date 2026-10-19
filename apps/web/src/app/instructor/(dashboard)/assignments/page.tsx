import type { Metadata } from "next";
import { Suspense } from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { AssignmentsView } from "./assignments-view";

export const metadata: Metadata = { title: "Assignments" };

export default function InstructorAssignmentsPage() {
  return (
    <Suspense fallback={<Skeleton className="m-8 h-64" />}>
      <AssignmentsView />
    </Suspense>
  );
}
