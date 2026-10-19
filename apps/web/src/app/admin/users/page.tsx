"use client";

import { useDeferredValue, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Search } from "lucide-react";
import type { components } from "@erudsway/api-client";
import { AdminPageHeader, formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { useMe } from "@/lib/auth/session";
import { CourseAccessSheet } from "./course-access";

type AdminUser = components["schemas"]["AdminUserResource"];

const roleFilters = [
  { value: "", label: "All roles" },
  { value: "student", label: "Students" },
  { value: "instructor", label: "Instructors" },
  { value: "admin", label: "Admins" },
];

export default function UsersPage() {
  return <RequireAuth permission="manage_users">{() => <UsersTable />}</RequireAuth>;
}

function UsersTable() {
  const [q, setQ] = useState("");
  const [role, setRole] = useState("");
  const [page, setPage] = useState(1);
  const [target, setTarget] = useState<AdminUser | null>(null);
  const [accessFor, setAccessFor] = useState<AdminUser | null>(null);
  const deferredQ = useDeferredValue(q.trim());
  const { data: me } = useMe();
  const queryClient = useQueryClient();

  const list = useQuery({
    queryKey: ["admin", "users", deferredQ, role, page],
    queryFn: () =>
      unwrap(
        api.GET("/admin/users", {
          params: {
            query: {
              ...(deferredQ && { q: deferredQ }),
              ...(role && { role: role as "student" | "instructor" | "admin" }),
              page,
            },
          },
        }),
      ),
    placeholderData: (prev) => prev,
  });
  const rows = (list.data?.data ?? []) as AdminUser[];
  const meta = (list.data as { meta?: { total: number; last_page: number } } | undefined)?.meta;

  const reactivate = async (user: AdminUser) => {
    await mutate(() => api.POST("/admin/users/{user}/reactivate", { params: { path: { user: user.id } } }));
    await queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
  };

  return (
    <>
      <AdminPageHeader title="Users" description={meta ? `${meta.total.toLocaleString("en-IN")} accounts` : undefined} />
      <div className="px-8 py-6">
        <div className="mb-5 flex flex-wrap items-center gap-3">
          <div className="relative w-full max-w-sm">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-500" />
            <input
              type="search"
              value={q}
              onChange={(e) => {
                setQ(e.target.value);
                setPage(1);
              }}
              placeholder="Search name or email"
              aria-label="Search users"
              className="h-10 w-full rounded-md border border-input bg-white pr-3 pl-9 text-sm focus:border-navy-900 focus:outline-none"
            />
          </div>
          <select
            aria-label="Filter by role"
            value={role}
            onChange={(e) => {
              setRole(e.target.value);
              setPage(1);
            }}
            className="h-10 rounded-md border border-input bg-white px-3 text-sm"
          >
            {roleFilters.map((r) => (
              <option key={r.value} value={r.value}>
                {r.label}
              </option>
            ))}
          </select>
        </div>

        {list.isPending ? (
          <Skeleton className="h-64 w-full" />
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load users.</FormAlert>
        ) : (
          <div className="overflow-hidden rounded-md border border-line bg-white">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-line bg-paper-2/60 text-xs font-semibold tracking-wide text-stone-500 uppercase">
                <tr>
                  <th className="px-5 py-3">Name</th>
                  <th className="px-5 py-3">Roles</th>
                  <th className="px-5 py-3">Status</th>
                  <th className="hidden px-5 py-3 md:table-cell">Joined</th>
                  <th className="hidden px-5 py-3 md:table-cell">Last login</th>
                  <th className="px-5 py-3">
                    <span className="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {rows.length === 0 && (
                  <tr>
                    <td colSpan={6} className="px-5 py-12 text-center text-stone-500">
                      No users match.
                    </td>
                  </tr>
                )}
                {rows.map((u) => (
                  <tr key={u.id} className="hover:bg-paper">
                    <td className="px-5 py-3">
                      <p className="font-semibold text-ink">{u.name}</p>
                      <p className="text-xs text-stone-500">
                        {u.email}
                        {!u.email_verified && <span className="ml-1.5 text-gold-600">· unverified</span>}
                      </p>
                    </td>
                    <td className="px-5 py-3 text-stone-700 capitalize">{u.roles.join(", ")}</td>
                    <td className="px-5 py-3">
                      <StatusPill status={u.status} />
                    </td>
                    <td className="tabular hidden px-5 py-3 text-stone-700 md:table-cell">{formatDate(u.created_at)}</td>
                    <td className="tabular hidden px-5 py-3 text-stone-700 md:table-cell">{formatDate(u.last_login_at)}</td>
                    <td className="space-x-1 px-5 py-3 text-right whitespace-nowrap">
                      <Button size="sm" variant="outline" onClick={() => setAccessFor(u)}>
                        Courses
                      </Button>
                      {u.id !== me?.id && !u.roles.includes("admin") &&
                        (u.status === "suspended" ? (
                          <Button size="sm" variant="outline" onClick={() => reactivate(u)}>
                            Reactivate
                          </Button>
                        ) : (
                          <Button size="sm" variant="ghost" className="text-destructive" onClick={() => setTarget(u)}>
                            Suspend
                          </Button>
                        ))}
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

      <SuspendDialog user={target} onClose={() => setTarget(null)} />
      <CourseAccessSheet user={accessFor} onClose={() => setAccessFor(null)} />
    </>
  );
}

function SuspendDialog({ user, onClose }: { user: AdminUser | null; onClose: () => void }) {
  const queryClient = useQueryClient();
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    if (!user) return;
    setBusy(true);
    setError(null);
    try {
      await mutate(() =>
        api.POST("/admin/users/{user}/suspend", { params: { path: { user: user.id } }, body: { reason } }),
      );
      await queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
      setReason("");
      onClose();
    } catch (e) {
      setError(e instanceof ApiRequestError ? (e.fields.reason?.[0] ?? e.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={!!user} onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Suspend {user?.name}?</DialogTitle>
          <DialogDescription>
            They will be signed out everywhere and won&rsquo;t be able to log in until reactivated. Purchases are kept.
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-2">
          {error && <FormAlert>{error}</FormAlert>}
          <Label htmlFor="suspend-reason">Reason (recorded in the audit log)</Label>
          <Textarea id="suspend-reason" rows={3} value={reason} onChange={(e) => setReason(e.target.value)} />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button variant="destructive" onClick={submit} disabled={busy || reason.trim().length < 5}>
            {busy ? "Suspending…" : "Suspend account"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
