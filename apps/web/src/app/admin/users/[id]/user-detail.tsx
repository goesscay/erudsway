"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Eye, ShieldCheck } from "lucide-react";
import { formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  impersonate,
  useAdminUser,
  useUpdateAccess,
  type AdminUserDetail,
  type StaffPermission,
} from "@/lib/admin/api";
import { ApiRequestError } from "@/lib/api/client";
import type { CurrentUser } from "@/lib/auth/session";
import { formatCount, formatPaise } from "@/lib/format";
import { CourseAccessSheet } from "../course-access";

const PERMISSIONS: { key: StaffPermission; label: string; help: string }[] = [
  {
    key: "review_instructor_applications",
    label: "Instructor applications",
    help: "Approve or reject people applying to teach.",
  },
  {
    key: "moderate_courses",
    label: "Course and review moderation",
    help: "Review courses before they go live; hide reported reviews and Q&A.",
  },
  {
    key: "manage_courses",
    label: "Course management",
    help: "Edit any course in the builder, publish, archive or feature it, and change its instructor.",
  },
  {
    key: "manage_categories",
    label: "Categories",
    help: "Add, rename, reorder, hide and delete catalogue categories.",
  },
  { key: "manage_users", label: "Users", help: "Find users, suspend them, grant or revoke course access." },
  { key: "manage_finance", label: "Finance", help: "Orders, refunds, coupons, payouts and the ledger." },
  { key: "view_reports", label: "Reports", help: "The dashboard numbers and every report, with CSV exports." },
  { key: "manage_settings", label: "Settings", help: "GST, invoice, refund and payout settings." },
  { key: "view_audit_logs", label: "Audit log", help: "See and export the record of staff actions." },
];

export function UserDetail({ id }: { id: string }) {
  return <RequireAuth permission="manage_users">{(me) => <Detail id={id} me={me} />}</RequireAuth>;
}

function Detail({ id, me }: { id: string; me: CurrentUser }) {
  const user = useAdminUser(id);
  const [accessOpen, setAccessOpen] = useState(false);

  if (user.isPending) return <Skeleton className="m-8 h-96" />;
  if (user.isError) {
    return (
      <div className="p-8">
        <FormAlert>
          {user.error instanceof ApiRequestError && user.error.status === 404
            ? "No such user."
            : "Couldn’t load this user."}
        </FormAlert>
      </div>
    );
  }

  const u = user.data;
  const isAdmin = me.roles.includes("admin");
  const self = me.id === u.id;

  return (
    <>
      <header className="border-b border-line bg-white px-4 py-6 sm:px-8">
        <Link
          href="/admin/users"
          className="inline-flex items-center gap-1 text-sm font-semibold text-navy-800 hover:underline"
        >
          <ArrowLeft className="size-4" /> Users
        </Link>
        <div className="mt-4 flex flex-wrap items-center gap-4">
          <InstructorAvatar name={u.name} className="size-14 text-lg" />
          <div className="min-w-0 flex-1">
            <h1 className="flex flex-wrap items-center gap-3 text-h2">
              {u.name}
              <StatusPill status={u.status} />
            </h1>
            <p className="text-sm text-stone-500">
              {u.email}
              {!u.email_verified && <span className="ml-1.5 text-gold-600">· email not verified</span>}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => setAccessOpen(true)}>
              Course access
            </Button>
            {isAdmin && !self && <ViewAs user={u} />}
          </div>
        </div>
      </header>

      <div className="grid gap-6 px-4 py-6 sm:px-8 xl:grid-cols-[1fr_380px]">
        <div className="space-y-6">
          <section aria-label="Summary" className="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <Stat label="Courses" value={formatCount(u.stats.enrollments)} />
            <Stat label="Completed" value={formatCount(u.stats.completed)} />
            <Stat label="Certificates" value={formatCount(u.stats.certificates)} />
            <Stat label="Orders" value={formatCount(u.stats.orders)} />
            <Stat label="Spent" value={formatPaise(u.stats.spent)} />
          </section>

          <section className="rounded-md border border-line bg-white">
            <h2 className="border-b border-line px-5 py-3.5 font-sans text-base font-bold text-ink">Recent orders</h2>
            {u.recent_orders.length === 0 ? (
              <p className="px-5 py-6 text-sm text-stone-500">No paid orders.</p>
            ) : (
              <ul className="divide-y divide-line text-sm">
                {u.recent_orders.map((o) => (
                  <li key={o.number} className="flex flex-wrap items-center gap-4 px-5 py-3">
                    <span className="font-mono text-xs font-semibold text-ink">{o.number}</span>
                    <span className="tabular text-stone-500">{formatDate(o.paid_at)}</span>
                    <StatusPill status={o.status.replace("_", " ")} />
                    <span className="tabular ml-auto font-semibold">{formatPaise(o.total, true)}</span>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section className="rounded-md border border-line bg-white">
            <h2 className="border-b border-line px-5 py-3.5 font-sans text-base font-bold text-ink">Recent activity</h2>
            {u.activity.length === 0 ? (
              <p className="px-5 py-6 text-sm text-stone-500">No audited actions by or about this user.</p>
            ) : (
              <ul className="divide-y divide-line text-sm">
                {u.activity.map((a, i) => (
                  <li key={i} className="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-2.5">
                    <span className="tabular w-28 text-xs text-stone-500">{formatDate(a.created_at)}</span>
                    <span className="font-mono text-xs font-semibold text-navy-900">{a.action}</span>
                    <span className="text-xs text-stone-500">
                      {a.about_this_user ? `by ${a.by ?? "the system"}` : "by this user"}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>

        <aside className="space-y-6">
          <section className="rounded-md border border-line bg-white px-5 py-4 text-sm">
            <dl className="space-y-2">
              <Row label="Joined">{formatDate(u.created_at)}</Row>
              <Row label="Last sign-in">{formatDate(u.last_login_at)}</Row>
              <Row label="Roles">
                <span className="capitalize">{u.roles.join(", ") || "—"}</span>
              </Row>
              {u.instructor && (
                <Row label="Teaches as">
                  {u.instructor.slug ? (
                    <Link
                      href={`/instructors/${u.instructor.slug}`}
                      className="font-semibold text-navy-800 hover:underline"
                    >
                      {u.instructor.display_name}
                    </Link>
                  ) : (
                    u.instructor.display_name
                  )}
                </Row>
              )}
            </dl>
          </section>
          <StaffAccess user={u} canEdit={isAdmin && !self} self={self} />
        </aside>
      </div>

      <CourseAccessSheet
        user={accessOpen ? { id: u.id, name: u.name, email: u.email } : null}
        onClose={() => setAccessOpen(false)}
      />
    </>
  );
}

function ViewAs({ user }: { user: AdminUserDetail }) {
  const queryClient = useQueryClient();
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!user.can_impersonate) {
    return (
      <Button variant="outline" disabled title="Staff and suspended accounts can’t be viewed as.">
        <Eye /> View as
      </Button>
    );
  }

  return (
    <>
      <Button
        variant="outline"
        disabled={busy}
        onClick={async () => {
          if (
            !window.confirm(
              `View the site as ${user.name}? You’ll see what they see, but you can’t change anything for them. This is recorded in the audit log.`,
            )
          )
            return;
          setBusy(true);
          setError(null);
          try {
            await impersonate(user.id);
            // Everything cached belonged to the admin's own account.
            queryClient.clear();
            router.replace("/my-learning");
          } catch (e) {
            setError(e instanceof ApiRequestError ? e.message : "Couldn’t start.");
            setBusy(false);
          }
        }}
      >
        <Eye /> {busy ? "Switching…" : "View as"}
      </Button>
      {error && <p className="w-full text-xs text-destructive">{error}</p>}
    </>
  );
}

function StaffAccess({ user, canEdit, self }: { user: AdminUserDetail; canEdit: boolean; self: boolean }) {
  const update = useUpdateAccess(user.id);
  const [admin, setAdmin] = useState(user.is_admin);
  const [perms, setPerms] = useState<StaffPermission[]>(user.direct_permissions as StaffPermission[]);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const dirty = admin !== user.is_admin || [...perms].sort().join() !== [...user.direct_permissions].sort().join();

  const save = () => {
    const summary = admin
      ? `Make ${user.name} an admin? Admins can do everything, including changing other staff’s access.`
      : perms.length === 0
        ? `Remove all staff access from ${user.name}?`
        : `Give ${user.name} access to: ${PERMISSIONS.filter((p) => perms.includes(p.key))
            .map((p) => p.label)
            .join(", ")}?`;
    if (!window.confirm(summary)) return;
    setError(null);
    setSaved(false);
    update.mutate(
      { admin, permissions: admin ? [] : perms },
      {
        onSuccess: () => setSaved(true),
        onError: (e) => setError(e instanceof ApiRequestError ? e.message : "Couldn’t save."),
      },
    );
  };

  return (
    <section className="rounded-md border border-line bg-white">
      <h2 className="flex items-center gap-2 border-b border-line px-5 py-3.5 font-sans text-base font-bold text-ink">
        <ShieldCheck className="size-4 text-gold-600" /> Staff access
      </h2>
      <div className="space-y-3 px-5 py-4 text-sm">
        {!canEdit && (
          <p className="text-xs text-stone-500">
            {self ? "You can’t change your own access. Ask another admin." : "Only admins can change staff access."}
          </p>
        )}
        {error && <FormAlert>{error}</FormAlert>}
        {saved && !dirty && <FormAlert tone="success">Access updated.</FormAlert>}
        <label className="flex items-start gap-3">
          <input
            type="checkbox"
            checked={admin}
            disabled={!canEdit}
            onChange={(e) => setAdmin(e.target.checked)}
            className="mt-0.5 size-4 accent-[var(--navy-900)]"
          />
          <span>
            <span className="block font-semibold text-ink">Administrator</span>
            <span className="block text-xs text-stone-500">
              Every permission, plus managing staff access and viewing as users.
            </span>
          </span>
        </label>
        <fieldset disabled={!canEdit || admin} className="space-y-2.5 border-t border-line pt-3 disabled:opacity-60">
          <legend className="sr-only">Individual permissions</legend>
          {PERMISSIONS.map((p) => (
            <label key={p.key} className="flex items-start gap-3">
              <input
                type="checkbox"
                checked={admin || perms.includes(p.key)}
                onChange={(e) => setPerms(e.target.checked ? [...perms, p.key] : perms.filter((x) => x !== p.key))}
                className="mt-0.5 size-4 accent-[var(--navy-900)]"
              />
              <span>
                <span className="block font-semibold text-ink">{p.label}</span>
                <span className="block text-xs text-stone-500">{p.help}</span>
              </span>
            </label>
          ))}
        </fieldset>
        {canEdit && (
          <Button size="sm" onClick={save} disabled={!dirty || update.isPending}>
            {update.isPending ? "Saving…" : "Save access"}
          </Button>
        )}
      </div>
    </section>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border border-line bg-white px-4 py-3">
      <p className="text-xs font-semibold tracking-wide text-stone-500 uppercase">{label}</p>
      <p className="tabular mt-0.5 font-serif text-xl font-semibold text-ink">{value}</p>
    </div>
  );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="grid grid-cols-[100px_1fr] gap-3">
      <dt className="text-stone-500">{label}</dt>
      <dd className="text-ink">{children}</dd>
    </div>
  );
}
