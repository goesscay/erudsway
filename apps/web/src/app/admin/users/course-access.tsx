"use client";

import { useDeferredValue, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import type { components } from "@erudsway/api-client";
import { formatDate, StatusPill } from "@/components/admin/page-header";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import type { Enrollment } from "@/lib/learning/api";
import { cn } from "@/lib/utils";

type AdminUser = components["schemas"]["AdminUserResource"];

const sourceLabel: Record<Enrollment["source"], string> = {
  purchase: "Purchased",
  free: "Free enrollment",
  coupon: "Coupon",
  admin_grant: "Granted by staff",
};

/**
 * Staff view of one user's courses: grant access (scholarships, corporate batches, support
 * fixes) or remove it. Every change needs a reason and is written to the audit log.
 */
export function CourseAccessSheet({ user, onClose }: { user: AdminUser | null; onClose: () => void }) {
  return (
    <Sheet open={!!user} onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
        {user && <Access user={user} />}
      </SheetContent>
    </Sheet>
  );
}

function Access({ user }: { user: AdminUser }) {
  const key = ["admin", "user-enrollments", user.id];
  const queryClient = useQueryClient();
  const list = useQuery({
    queryKey: key,
    queryFn: async () => (await unwrap(api.GET("/admin/users/{user}/enrollments", { params: { path: { user: user.id } } }))).data,
  });
  const refresh = () => queryClient.invalidateQueries({ queryKey: key });

  return (
    <>
      <SheetHeader className="border-b border-line">
        <SheetTitle className="font-sans text-lg font-bold text-ink">Course access</SheetTitle>
        <SheetDescription>
          {user.name} ({user.email})
        </SheetDescription>
      </SheetHeader>
      <div className="space-y-8 px-4 pb-8">
        <GrantForm userId={user.id} onGranted={refresh} />

        <section>
          <h3 className="mb-3 font-sans text-xs font-semibold tracking-wide text-stone-500 uppercase">Courses</h3>
          {list.isPending ? (
            <Skeleton className="h-24 w-full" />
          ) : list.isError ? (
            <FormAlert>Couldn&rsquo;t load this user&rsquo;s courses.</FormAlert>
          ) : list.data.length === 0 ? (
            <p className="rounded-md border border-dashed border-line px-4 py-8 text-center text-sm text-stone-500">Not enrolled in any course.</p>
          ) : (
            <ul className="divide-y divide-line rounded-md border border-line bg-white">
              {list.data.map((e) => (
                <EnrollmentRow key={e.id} enrollment={e} onRevoked={refresh} />
              ))}
            </ul>
          )}
        </section>
      </div>
    </>
  );
}

function GrantForm({ userId, onGranted }: { userId: string; onGranted: () => void }) {
  const [search, setSearch] = useState("");
  const q = useDeferredValue(search.trim());
  const [course, setCourse] = useState<{ slug: string; title: string } | null>(null);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const results = useQuery({
    queryKey: ["admin", "course-search", q],
    queryFn: async () => (await unwrap(api.GET("/courses", { params: { query: { q, per_page: 6 } } }))).data,
    enabled: q.length >= 2 && !course,
  });

  const grant = async () => {
    if (!course) return;
    setBusy(true);
    setError(null);
    try {
      await mutate(() => api.POST("/admin/users/{user}/enrollments", { params: { path: { user: userId } }, body: { course: course.slug, reason: reason.trim() } }));
      setCourse(null);
      setSearch("");
      setReason("");
      onGranted();
    } catch (e) {
      setError(e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="space-y-3 rounded-md border border-line bg-paper/60 p-4">
      <h3 className="font-sans text-sm font-bold text-ink">Grant access to a course</h3>
      {error && <FormAlert>{error}</FormAlert>}
      {course ? (
        <div className="flex items-center justify-between gap-3 rounded-md border border-line bg-white px-3 py-2 text-sm">
          <span className="font-semibold text-ink">{course.title}</span>
          <Button size="sm" variant="ghost" onClick={() => setCourse(null)}>
            Change
          </Button>
        </div>
      ) : (
        <div className="relative">
          <Label htmlFor="grant-course" className="sr-only">
            Course
          </Label>
          <Input id="grant-course" placeholder="Search published courses…" value={search} onChange={(e) => setSearch(e.target.value)} className="bg-white" autoComplete="off" />
          {q.length >= 2 && (
            <ul className="mt-1 max-h-56 overflow-y-auto rounded-md border border-line bg-white shadow-sm">
              {results.isPending ? (
                <li className="px-3 py-2 text-sm text-stone-500">Searching…</li>
              ) : (results.data ?? []).length === 0 ? (
                <li className="px-3 py-2 text-sm text-stone-500">No published course matches.</li>
              ) : (
                results.data!.map((c) => (
                  <li key={c.id}>
                    <button type="button" onClick={() => setCourse({ slug: c.slug, title: c.title })} className="block w-full px-3 py-2 text-left text-sm hover:bg-paper">
                      <span className="font-semibold text-ink">{c.title}</span>
                      <span className="block text-xs text-stone-500">{c.instructor.name}</span>
                    </button>
                  </li>
                ))
              )}
            </ul>
          )}
        </div>
      )}
      <div>
        <Label htmlFor="grant-reason" className="mb-1.5 text-xs font-semibold text-stone-700">
          Reason (recorded in the audit log)
        </Label>
        <Textarea id="grant-reason" rows={2} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="e.g. ICAI scholarship batch, Oct 2026" className="bg-white" />
      </div>
      <Button onClick={grant} disabled={!course || reason.trim().length < 5 || busy}>
        {busy ? "Granting…" : "Grant access"}
      </Button>
    </section>
  );
}

function EnrollmentRow({ enrollment: e, onRevoked }: { enrollment: Enrollment; onRevoked: () => void }) {
  const [revoking, setRevoking] = useState(false);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const revoke = async () => {
    setBusy(true);
    setError(null);
    try {
      await mutate(() => api.POST("/admin/enrollments/{enrollment}/revoke", { params: { path: { enrollment: e.id } }, body: { reason: reason.trim() } }));
      onRevoked();
    } catch (err) {
      setError(err instanceof ApiRequestError ? (Object.values(err.fields)[0]?.[0] ?? err.message) : "Something went wrong.");
      setBusy(false);
    }
  };

  return (
    <li className="space-y-2 px-4 py-3 text-sm">
      <div className="flex items-start gap-3">
        <div className="min-w-0 flex-1">
          <p className={cn("font-semibold", e.status === "active" ? "text-ink" : "text-stone-500")}>{e.course.title}</p>
          <p className="mt-0.5 text-xs text-stone-500">
            {sourceLabel[e.source]} · {formatDate(e.granted_at)}
            {e.status === "active" && <> · {e.progress_percent}% complete</>}
            {e.revoked_at && <> · removed {formatDate(e.revoked_at)}</>}
          </p>
          {e.reason && <p className="mt-1 text-xs text-stone-700">“{e.reason}”</p>}
        </div>
        <StatusPill status={e.status} />
        {e.status === "active" && !revoking && (
          <Button size="sm" variant="ghost" className="text-destructive" onClick={() => setRevoking(true)}>
            Remove
          </Button>
        )}
      </div>
      {revoking && (
        <div className="space-y-2 rounded-md bg-paper p-3">
          {error && <FormAlert>{error}</FormAlert>}
          <Label htmlFor={`revoke-${e.id}`} className="text-xs font-semibold text-stone-700">
            Why is access being removed?
          </Label>
          <Textarea id={`revoke-${e.id}`} rows={2} value={reason} onChange={(ev) => setReason(ev.target.value)} className="bg-white" />
          <div className="flex gap-2">
            <Button size="sm" variant="destructive" disabled={reason.trim().length < 5 || busy} onClick={revoke}>
              {busy ? "Removing…" : "Remove access"}
            </Button>
            <Button size="sm" variant="outline" onClick={() => setRevoking(false)}>
              Cancel
            </Button>
          </div>
        </div>
      )}
    </li>
  );
}
