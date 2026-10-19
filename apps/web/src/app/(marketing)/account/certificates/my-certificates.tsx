"use client";

import Link from "next/link";
import { Award, Download } from "lucide-react";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useMyCertificates } from "@/lib/assessment/api";
import { formatDuration } from "@/lib/format";

export function MyCertificates() {
  return <RequireAuth>{() => <List />}</RequireAuth>;
}

function List() {
  const list = useMyCertificates();

  return (
    <div className="mx-auto max-w-4xl px-6 py-12">
      <h1 className="text-h2">My certificates</h1>
      <p className="mt-1 text-sm text-stone-500">
        You earn a certificate when you complete every lecture of a course. Each one has a public link anyone can use to
        check it.
      </p>

      <div className="mt-8">
        {list.isPending ? (
          <Skeleton className="h-40 w-full" />
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load your certificates.</FormAlert>
        ) : list.data.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center">
            <Award className="mx-auto size-9 text-stone-300" strokeWidth={1.5} />
            <p className="mt-3 text-sm text-stone-700">No certificates yet. Finish a course to earn one.</p>
            <Link href="/my-learning" className={buttonVariants({ variant: "outline", className: "mt-5" })}>
              Go to My learning
            </Link>
          </div>
        ) : (
          <ul className="divide-y divide-line rounded-md border border-line bg-white">
            {list.data.map((c) => (
              <li key={c.code} className="flex flex-wrap items-center gap-4 px-5 py-4">
                <Award className="size-8 shrink-0 text-gold-600" strokeWidth={1.5} />
                <div className="min-w-0 flex-1">
                  <Link
                    href={`/certificates/${c.code}`}
                    className="font-semibold text-ink hover:text-navy-800 hover:underline"
                  >
                    {c.course_title}
                  </Link>
                  <p className="text-xs text-stone-500">
                    {new Date(c.issued_at).toLocaleDateString("en-IN", {
                      day: "numeric",
                      month: "short",
                      year: "numeric",
                    })}{" "}
                    · {formatDuration(c.duration_seconds)} · {c.serial}
                    {!c.valid && <span className="ml-1 font-semibold text-destructive">· no longer valid</span>}
                  </p>
                </div>
                {c.valid && (
                  <a href={c.pdf_url} className={buttonVariants({ variant: "outline", size: "sm" })}>
                    <Download /> PDF
                  </a>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
