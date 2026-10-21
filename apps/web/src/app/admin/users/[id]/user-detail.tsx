"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Ban, Eye, HandCoins, Mail, Pencil, RotateCcw, ShieldCheck } from "lucide-react";
import { OfflinePaymentSheet } from "@/components/admin/offline-payment-sheet";
import { formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  impersonate,
  resendInvite,
  roleLabel,
  useAdminUser,
  usePermissions,
  useRoles,
  useUpdateAccess,
  type AdminUserDetail,
  type StaffPermission,
} from "@/lib/admin/api";
import { ApiRequestError } from "@/lib/api/client";
import type { CurrentUser } from "@/lib/auth/session";
import { formatCount, formatPaise } from "@/lib/format";
import { CourseAccessSheet } from "../course-access";
import { EditDetailsDialog, reactivateUser, SuspendDialog } from "../user-dialogs";

export function UserDetail({ id }: { id: string }) {
  return <RequireAuth permission="manage_users">{(me) => <Detail id={id} me={me} />}</RequireAuth>;
}

function Detail({ id, me }: { id: string; me: CurrentUser }) {
  const user = useAdminUser(id);
  const queryClient = useQueryClient();
  const [accessOpen, setAccessOpen] = useState(false);
  const [editing, setEditing] = useState(false);
  const [suspending, setSuspending] = useState(false);
  const [recording, setRecording] = useState(false);
  const [notice, setNotice] = useState<{ tone: "success" | "error"; text: string } | null>(null);

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
  const isSuper = me.roles.includes("super_admin");
  const self = me.id === u.id;
  const canEditDetails = self || isSuper || !u.is_admin;
  const canSuspend = !self && !u.is_admin;
  const person = { id: u.id, name: u.name, email: u.email };

  const sendInvite = async () => {
    setNotice(null);
    try {
      await resendInvite(u.id);
      setNotice({ tone: "success", text: `Sent a new “set your password” email to ${u.email}.` });
    } catch (e) {
      setNotice({ tone: "error", text: e instanceof ApiRequestError ? e.message : "Couldn’t send the email." });
    }
  };

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
              {u.is_super_admin ? (
                <span className="inline-flex h-6 items-center rounded-sm bg-navy-900 px-2 text-[0.7rem] font-bold tracking-wide text-white">
                  Super admin
                </span>
              ) : (
                u.is_admin && (
                  <span className="inline-flex h-6 items-center rounded-sm bg-navy-100 px-2 text-[0.7rem] font-bold tracking-wide text-navy-800">
                    Admin
                  </span>
                )
              )}
            </h1>
            <p className="text-sm text-stone-500">
              {u.email}
              {!u.email_verified && <span className="ml-1.5 text-gold-600">· email not verified</span>}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {canEditDetails && (
              <Button variant="outline" onClick={() => setEditing(true)}>
                <Pencil /> Edit details
              </Button>
            )}
            <Button variant="outline" onClick={() => setAccessOpen(true)}>
              Course access
            </Button>
            {me.permissions.includes("manage_finance") && u.status === "active" && (
              <Button variant="outline" onClick={() => setRecording(true)}>
                <HandCoins /> Record offline payment
              </Button>
            )}
            {isAdmin && !self && <ViewAs user={u} />}
            {u.invite_pending && (
              <Button variant="outline" onClick={sendInvite} title="They haven’t signed in yet">
                <Mail /> Resend invite
              </Button>
            )}
            {canSuspend &&
              (u.status === "suspended" ? (
                <Button
                  variant="outline"
                  onClick={async () => {
                    setNotice(null);
                    try {
                      await reactivateUser(u.id, queryClient);
                    } catch (e) {
                      setNotice({
                        tone: "error",
                        text: e instanceof ApiRequestError ? e.message : "Couldn’t reactivate.",
                      });
                    }
                  }}
                >
                  <RotateCcw /> Reactivate
                </Button>
              ) : (
                <Button variant="outline" className="text-destructive" onClick={() => setSuspending(true)}>
                  <Ban /> Suspend
                </Button>
              ))}
          </div>
        </div>
        {notice && (
          <div className="mt-4">
            <FormAlert tone={notice.tone === "success" ? "success" : undefined}>{notice.text}</FormAlert>
          </div>
        )}
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
              <Row label="Roles">{u.roles.map(roleLabel).join(", ") || "—"}</Row>
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
          <StaffAccess
            user={u}
            canEdit={isAdmin && !self && (isSuper || !u.is_admin)}
            canManageAdmins={isSuper}
            self={self}
          />
        </aside>
      </div>

      <CourseAccessSheet user={accessOpen ? person : null} onClose={() => setAccessOpen(false)} />
      <EditDetailsDialog user={editing ? person : null} onClose={() => setEditing(false)} />
      <SuspendDialog user={suspending ? person : null} onClose={() => setSuspending(false)} />
      <OfflinePaymentSheet open={recording} learner={person} onClose={() => setRecording(false)} />
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

function StaffAccess({
  user,
  canEdit,
  canManageAdmins,
  self,
}: {
  user: AdminUserDetail;
  canEdit: boolean;
  canManageAdmins: boolean;
  self: boolean;
}) {
  const update = useUpdateAccess(user.id);
  const permissionList = usePermissions();
  const roleList = useRoles();
  const customRoles = (roleList.data ?? []).filter((r) => !r.built_in);
  const [admin, setAdmin] = useState(user.is_admin);
  const [superAdmin, setSuperAdmin] = useState(user.is_super_admin);
  const [roles, setRoles] = useState<string[]>(user.custom_roles);
  const [perms, setPerms] = useState<StaffPermission[]>(user.direct_permissions as StaffPermission[]);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const dirty =
    admin !== user.is_admin ||
    superAdmin !== user.is_super_admin ||
    [...roles].sort().join() !== [...user.custom_roles].sort().join() ||
    [...perms].sort().join() !== [...user.direct_permissions].sort().join();
  // Permissions the chosen roles give (so a direct tick isn't needed for them).
  const fromRoles = (key: string) =>
    customRoles.filter((r) => roles.includes(r.name) && r.permissions.includes(key)).map((r) => r.name);

  const save = () => {
    const summary = superAdmin
      ? `Make ${user.name} a super admin? Super admins can do everything, including adding and removing admins.`
      : admin
        ? `Make ${user.name} an admin? Admins can do everything except manage other admins.`
        : perms.length === 0 && roles.length === 0
          ? `Remove all staff access from ${user.name}?`
          : `Give ${user.name} ${[
              roles.length ? `the role${roles.length > 1 ? "s" : ""} ${roles.join(", ")}` : "",
              perms.length
                ? `these permissions directly: ${(permissionList.data ?? [])
                    .filter((p) => perms.includes(p.key))
                    .map((p) => p.label)
                    .join(", ")}`
                : "",
            ]
              .filter(Boolean)
              .join(", and ")}?`;
    if (!window.confirm(summary)) return;
    setError(null);
    setSaved(false);
    update.mutate(
      {
        admin: admin || superAdmin,
        ...(canManageAdmins ? { super_admin: superAdmin } : {}),
        roles: admin || superAdmin ? [] : roles,
        permissions: admin || superAdmin ? [] : perms,
      },
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
            {self
              ? "You can’t change your own access. Ask another admin."
              : user.is_admin
                ? "Only a super admin can change an admin’s access."
                : "Only admins can change staff access."}
          </p>
        )}
        {error && <FormAlert>{error}</FormAlert>}
        {saved && !dirty && <FormAlert tone="success">Access updated.</FormAlert>}
        <label className="flex items-start gap-3">
          <input
            type="checkbox"
            checked={superAdmin}
            disabled={!canEdit || !canManageAdmins}
            onChange={(e) => {
              setSuperAdmin(e.target.checked);
              if (e.target.checked) setAdmin(true);
            }}
            className="mt-0.5 size-4 accent-[var(--navy-900)]"
          />
          <span>
            <span className="block font-semibold text-ink">Super admin</span>
            <span className="block text-xs text-stone-500">
              Everything an admin can do, plus adding, removing and changing admins.
            </span>
          </span>
        </label>
        <label className="flex items-start gap-3">
          <input
            type="checkbox"
            checked={admin || superAdmin}
            disabled={!canEdit || !canManageAdmins || superAdmin}
            onChange={(e) => setAdmin(e.target.checked)}
            className="mt-0.5 size-4 accent-[var(--navy-900)]"
          />
          <span>
            <span className="block font-semibold text-ink">Administrator</span>
            <span className="block text-xs text-stone-500">
              Every permission, plus giving staff permissions and viewing as users. Only a super admin can make admins.
            </span>
          </span>
        </label>
        <fieldset
          disabled={!canEdit || admin || superAdmin}
          className="space-y-2.5 border-t border-line pt-3 disabled:opacity-60"
        >
          <legend className="float-left mb-1 w-full text-xs font-bold tracking-wide text-stone-500 uppercase">
            Staff roles
          </legend>
          {customRoles.length === 0 ? (
            <p className="clear-left text-xs text-stone-500">
              No custom roles yet.{" "}
              <Link href="/admin/roles" className="font-semibold text-navy-800 hover:underline">
                Roles &amp; permissions
              </Link>
            </p>
          ) : (
            customRoles.map((r) => (
              <label key={r.id} className="clear-left flex items-start gap-3">
                <input
                  type="checkbox"
                  checked={roles.includes(r.name)}
                  onChange={(e) => setRoles(e.target.checked ? [...roles, r.name] : roles.filter((x) => x !== r.name))}
                  className="mt-0.5 size-4 accent-[var(--navy-900)]"
                />
                <span>
                  <span className="block font-semibold text-ink">{r.name}</span>
                  <span className="block text-xs text-stone-500">
                    {r.description ? `${r.description} · ` : ""}
                    {r.permissions.length} permission{r.permissions.length === 1 ? "" : "s"}
                  </span>
                </span>
              </label>
            ))
          )}
        </fieldset>
        <fieldset
          disabled={!canEdit || admin || superAdmin}
          className="space-y-2.5 border-t border-line pt-3 disabled:opacity-60"
        >
          <legend className="float-left mb-1 w-full text-xs font-bold tracking-wide text-stone-500 uppercase">
            Permissions given directly
          </legend>
          {(permissionList.data ?? []).map((p) => {
            const via = admin || superAdmin ? [] : fromRoles(p.key);
            return (
              <label key={p.key} className="clear-left flex items-start gap-3">
                <input
                  type="checkbox"
                  checked={admin || superAdmin || perms.includes(p.key)}
                  onChange={(e) => setPerms(e.target.checked ? [...perms, p.key] : perms.filter((x) => x !== p.key))}
                  className="mt-0.5 size-4 accent-[var(--navy-900)]"
                />
                <span>
                  <span className="block font-semibold text-ink">{p.label}</span>
                  <span className="block text-xs text-stone-500">{p.description}</span>
                  {via.length > 0 && (
                    <span className="mt-0.5 block text-xs font-semibold text-success">Also from: {via.join(", ")}</span>
                  )}
                </span>
              </label>
            );
          })}
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
