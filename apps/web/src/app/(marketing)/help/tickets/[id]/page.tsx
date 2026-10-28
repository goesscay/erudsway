import { Suspense } from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { TicketView } from "./ticket-view";

export default function TicketPage({ params }: { params: Promise<{ id: string }> }) {
  return (
    <Suspense fallback={<Skeleton className="mx-auto my-10 h-96 max-w-4xl" />}>
      <Resolved params={params} />
    </Suspense>
  );
}

async function Resolved({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <TicketView id={id} />;
}
