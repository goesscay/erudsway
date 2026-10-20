"use client";

import Image from "next/image";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { CheckCircle2, Download } from "lucide-react";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { api, unwrap } from "@/lib/api/client";
import { formatPaise } from "@/lib/format";

export function OrderComplete() {
  const id = useSearchParams().get("order");
  return <RequireAuth>{() => (id ? <Confirmation id={id} /> : <FormAlert>Order not found.</FormAlert>)}</RequireAuth>;
}

function Confirmation({ id }: { id: string }) {
  const order = useQuery({
    queryKey: ["order", id],
    queryFn: async () => (await unwrap(api.GET("/orders/{order}", { params: { path: { order: id } } }))).data,
  });

  return (
    <div className="mx-auto max-w-2xl px-4 py-16 sm:px-6">
      {order.isPending ? (
        <Skeleton className="h-72 w-full" />
      ) : order.isError ? (
        <FormAlert>We couldn&rsquo;t load this order.</FormAlert>
      ) : (
        <div className="rounded-md border border-line bg-white p-8 text-center sm:p-10">
          <CheckCircle2 className="mx-auto size-12 text-success" strokeWidth={1.5} />
          <h1 className="mt-4 text-h2">{order.data.items.length === 1 ? "You’re enrolled" : "You’re all set"}</h1>
          <p className="mt-2 text-sm text-stone-700">
            Order {order.data.number} · {formatPaise(order.data.total)} paid. We&rsquo;ve emailed your receipt
            {order.data.invoice ? " and GST invoice" : ""}.
          </p>

          <ul className="mt-8 space-y-3 text-left">
            {order.data.items.map((item) => (
              <li key={item.id} className="flex items-center gap-4 rounded-md border border-line p-3">
                <div className="relative aspect-video w-24 shrink-0 overflow-hidden rounded-sm bg-paper-2">
                  {item.course.thumbnail_url && (
                    <Image src={item.course.thumbnail_url} alt="" fill sizes="96px" className="object-cover" />
                  )}
                </div>
                <p className="line-clamp-2 flex-1 text-sm font-semibold text-ink">{item.course.title}</p>
                <Link href={`/learn/${item.course.slug}`} className={buttonVariants({ size: "sm" })}>
                  Start learning
                </Link>
              </li>
            ))}
          </ul>

          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Link href="/my-learning" className={buttonVariants({ variant: "outline" })}>
              Go to My learning
            </Link>
            {order.data.invoice && (
              <a href={order.data.invoice.download_url} className={buttonVariants({ variant: "ghost" })}>
                <Download /> Tax invoice
              </a>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
