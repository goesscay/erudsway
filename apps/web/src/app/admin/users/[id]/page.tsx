import type { Metadata } from "next";
import { Suspense } from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { UserDetail } from "./user-detail";

export const metadata: Metadata = { title: "User" };

export default function AdminUserPage({ params }: { params: Promise<{ id: string }> }) {
  return (
    <Suspense fallback={<Skeleton className="m-8 h-96" />}>
      <Resolved params={params} />
    </Suspense>
  );
}

async function Resolved({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <UserDetail id={id} />;
}
