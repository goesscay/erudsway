"use client";

import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ExternalLink } from "lucide-react";
import type { components } from "@erudsway/api-client";
import { AdminPageHeader, formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { teachingExperience } from "@/lib/validation/auth";
import { cn } from "@/lib/utils";

type Application = components["schemas"]["InstructorApplicationResource"];
type Status = "pending" | "approved" | "rejected";

const tabs: { value: Status; label: string }[] = [
  { value: "pending", label: "Awaiting review" },
  { value: "approved", label: "Approved" },
  { value: "rejected", label: "Rejected" },
];

export default function InstructorApplicationsPage() {
  return (
    <RequireAuth permission="review_instructor_applications">
      {() => <ApplicationsQueue />}
    </RequireAuth>
  );
}

function ApplicationsQueue() {
  const [status, setStatus] = useState<Status>("pending");
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<Application | null>(null);

  const list = useQuery({
    queryKey: ["admin", "applications", status, page],
    queryFn: () =>
      unwrap(api.GET("/admin/instructor-applications", { params: { query: { status, page } } })),
  });

  const rows = (list.data?.data ?? []) as Application[];
  const meta = (list.data as { meta?: { total: number; last_page: number } } | undefined)?.meta;

  return (
    <>
      <AdminPageHeader title="Instructor applications" description="Review who can publish courses on Erudsway." />

      <div className="px-8 py-6">
        <div role="tablist" aria-label="Application status" className="mb-6 flex gap-6 border-b border-line">
          {tabs.map((t) => (
            <button
              key={t.value}
              role="tab"
              aria-selected={status === t.value}
              onClick={() => {
                setStatus(t.value);
                setPage(1);
              }}
              className={cn(
                "-mb-px border-b-2 pb-3 text-sm font-semibold",
                status === t.value ? "border-gold-500 text-navy-900" : "border-transparent text-stone-500 hover:text-navy-900",
              )}
            >
              {t.label}
              {status === t.value && meta && <span className="tabular ml-2 text-xs text-stone-500">{meta.total}</span>}
            </button>
          ))}
        </div>

        {list.isPending ? (
          <div className="space-y-2">
            {[0, 1, 2].map((i) => (
              <Skeleton key={i} className="h-16 w-full" />
            ))}
          </div>
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load applications. Refresh to try again.</FormAlert>
        ) : rows.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center">
            <p className="font-semibold text-ink">Nothing here</p>
            <p className="mt-1 text-sm text-stone-500">
              {status === "pending" ? "No applications are waiting for review." : `No ${status} applications yet.`}
            </p>
          </div>
        ) : (
          <div className="overflow-hidden rounded-md border border-line bg-white">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-line bg-paper-2/60 text-xs font-semibold tracking-wide text-stone-500 uppercase">
                <tr>
                  <th className="px-5 py-3">Applicant</th>
                  <th className="px-5 py-3">Headline</th>
                  <th className="hidden px-5 py-3 lg:table-cell">Topics</th>
                  <th className="px-5 py-3">{status === "pending" ? "Submitted" : "Reviewed"}</th>
                  <th className="px-5 py-3">
                    <span className="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {rows.map((a) => (
                  <tr key={a.id} className="hover:bg-paper">
                    <td className="px-5 py-3.5">
                      <div className="flex items-center gap-3">
                        <InstructorAvatar name={a.applicant?.name ?? "?"} className="size-9 text-xs" />
                        <div className="min-w-0">
                          <p className="truncate font-semibold text-ink">{a.applicant?.name}</p>
                          <p className="truncate text-xs text-stone-500">{a.applicant?.email}</p>
                        </div>
                      </div>
                    </td>
                    <td className="max-w-xs truncate px-5 py-3.5 text-stone-700">{a.headline}</td>
                    <td className="hidden px-5 py-3.5 text-stone-700 lg:table-cell">{a.topics.join(", ")}</td>
                    <td className="tabular px-5 py-3.5 whitespace-nowrap text-stone-700">
                      {formatDate(status === "pending" ? a.submitted_at : a.reviewed_at)}
                    </td>
                    <td className="px-5 py-3.5 text-right">
                      <Button size="sm" variant={status === "pending" ? "default" : "outline"} onClick={() => setSelected(a)}>
                        {status === "pending" ? "Review" : "View"}
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {meta && meta.last_page > 1 && (
          <div className="mt-4 flex items-center justify-end gap-3 text-sm">
            <span className="tabular text-stone-500">
              Page {page} of {meta.last_page}
            </span>
            <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              Previous
            </Button>
            <Button size="sm" variant="outline" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
              Next
            </Button>
          </div>
        )}
      </div>

      <Sheet open={!!selected} onOpenChange={(open) => !open && setSelected(null)}>
        <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
          {selected && <ApplicationReview application={selected} onDone={() => setSelected(null)} />}
        </SheetContent>
      </Sheet>
    </>
  );
}

function ApplicationReview({ application: a, onDone }: { application: Application; onDone: () => void }) {
  const queryClient = useQueryClient();
  const [notes, setNotes] = useState("");
  const [busy, setBusy] = useState<"approve" | "reject" | null>(null);
  const [error, setError] = useState<string | null>(null);
  const experienceLabel = teachingExperience.find((t) => t.value === a.teaching_experience)?.label ?? a.teaching_experience;

  const decide = async (decision: "approve" | "reject") => {
    setError(null);
    if (decision === "reject" && notes.trim().length < 10) {
      setError("Add a note of at least 10 characters explaining the decision — the applicant will see it.");
      return;
    }
    setBusy(decision);
    try {
      const path = decision === "approve"
        ? ("/admin/instructor-applications/{application}/approve" as const)
        : ("/admin/instructor-applications/{application}/reject" as const);
      await mutate(() =>
        api.POST(path, { params: { path: { application: a.id } }, body: { notes: notes.trim() || null } }),
      );
      await queryClient.invalidateQueries({ queryKey: ["admin", "applications"] });
      onDone();
    } catch (e) {
      setError(e instanceof ApiRequestError ? (e.fields.notes?.[0] ?? e.message) : "Something went wrong.");
    } finally {
      setBusy(null);
    }
  };

  return (
    <>
      <SheetHeader className="border-b border-line">
        <div className="flex items-center gap-3">
          <InstructorAvatar name={a.applicant?.name ?? "?"} className="size-12 text-sm" />
          <div>
            <SheetTitle className="font-sans text-lg font-bold text-ink">{a.applicant?.name}</SheetTitle>
            <SheetDescription>{a.applicant?.email}</SheetDescription>
          </div>
        </div>
      </SheetHeader>

      <div className="space-y-6 px-4 text-sm">
        <div className="flex items-center gap-3">
          <StatusPill status={a.status} />
          <span className="text-stone-500">Submitted {formatDate(a.submitted_at)}</span>
        </div>
        <Detail label="Headline">{a.headline}</Detail>
        <Detail label="Topics">
          <ul className="flex flex-wrap gap-1.5">
            {a.topics.map((t) => (
              <li key={t} className="rounded-sm bg-navy-100 px-2 py-0.5 text-xs font-semibold text-navy-800">
                {t}
              </li>
            ))}
          </ul>
        </Detail>
        <Detail label="Teaching experience">{experienceLabel}</Detail>
        <Detail label="Professional experience">
          <p className="leading-relaxed whitespace-pre-line text-stone-700">{a.experience}</p>
        </Detail>
        {a.sample_url && (
          <Detail label="Sample">
            <a href={a.sample_url} target="_blank" rel="noopener noreferrer nofollow" className="inline-flex items-center gap-1 font-semibold break-all text-navy-800 hover:underline">
              {a.sample_url} <ExternalLink className="size-3.5 shrink-0" />
            </a>
          </Detail>
        )}

        {a.status === "pending" ? (
          <div className="space-y-3 border-t border-line pt-6">
            {error && <FormAlert>{error}</FormAlert>}
            <Label htmlFor="review-notes" className="font-semibold text-ink">
              Notes to the applicant
            </Label>
            <Textarea
              id="review-notes"
              rows={4}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Required when rejecting. Optional welcome note when approving."
              className="bg-white"
            />
            <div className="flex gap-3 pt-1">
              <Button onClick={() => decide("approve")} disabled={!!busy}>
                {busy === "approve" ? "Approving…" : "Approve instructor"}
              </Button>
              <Button variant="destructive" onClick={() => decide("reject")} disabled={!!busy}>
                {busy === "reject" ? "Rejecting…" : "Reject"}
              </Button>
            </div>
            <p className="text-xs text-stone-500">The applicant is emailed either way. This action is recorded in the audit log.</p>
          </div>
        ) : (
          <div className="space-y-4 border-t border-line pt-6">
            <Detail label="Decision">
              <StatusPill status={a.status} /> by {a.reviewer?.name ?? "—"} on {formatDate(a.reviewed_at)}
            </Detail>
            {a.review_notes && <Detail label="Notes">{a.review_notes}</Detail>}
          </div>
        )}
      </div>
    </>
  );
}

function Detail({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <p className="mb-1 text-xs font-semibold tracking-wide text-stone-500 uppercase">{label}</p>
      <div className="text-ink">{children}</div>
    </div>
  );
}
