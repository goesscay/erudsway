"use client";

import Link from "next/link";
import { useState } from "react";
import { Lock, Pencil, Plus, Trash2, Users } from "lucide-react";
import { AdminPageHeader, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import {
  type AdminRole,
  type PermissionInfo,
  type StaffPermission,
  useDeleteRole,
  usePermissionHolders,
  usePermissions,
  useRoles,
  useSaveRole,
} from "@/lib/admin/api";
import { ApiRequestError } from "@/lib/api/client";
import type { CurrentUser } from "@/lib/auth/session";

type Editing = { mode: "new" } | { mode: "edit"; role: AdminRole };

function errorText(e: unknown) {
  return e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.";
}

function people(n: number) {
  return `${n.toLocaleString("en-IN")} ${n === 1 ? "person" : "people"}`;
}

export default function RolesPage() {
  return <RequireAuth permission="manage_users">{(me) => <Roles me={me} />}</RequireAuth>;
}

function Roles({ me }: { me: CurrentUser }) {
  const roles = useRoles();
  const permissions = usePermissions();
  const isSuper = me.roles.includes("super_admin");
  const [editing, setEditing] = useState<Editing | null>(null);
  const [deleting, setDeleting] = useState<AdminRole | null>(null);
  const [holdersOf, setHoldersOf] = useState<PermissionInfo | null>(null);
  const labels = new Map((permissions.data ?? []).map((p) => [p.key, p.label]));

  return (
    <>
      <AdminPageHeader
        title="Roles & permissions"
        description="A role is a named set of permissions. Give people roles on their user page."
      >
        {isSuper && (
          <Button onClick={() => setEditing({ mode: "new" })}>
            <Plus /> New role
          </Button>
        )}
      </AdminPageHeader>

      <div className="space-y-10 px-4 py-6 sm:px-8">
        <section aria-labelledby="roles-heading">
          <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="roles-heading" className="font-sans text-lg font-bold text-ink">
              Roles
            </h2>
            {!isSuper && (
              <p className="text-xs text-stone-500">Only a super admin can create, change or delete roles.</p>
            )}
          </div>
          {roles.isPending ? (
            <Skeleton className="h-64 w-full" />
          ) : roles.isError ? (
            <FormAlert>Couldn&rsquo;t load roles.</FormAlert>
          ) : (
            <ul className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
              {roles.data.map((role) => (
                <li key={role.id} className="flex flex-col rounded-md border border-line bg-white">
                  <div className="flex items-start gap-3 px-5 pt-4">
                    <div className="min-w-0 flex-1">
                      <h3 className="flex flex-wrap items-center gap-2 font-sans text-base font-bold text-ink">
                        {role.label}
                        {role.built_in && (
                          <span className="inline-flex items-center gap-1 rounded-sm bg-paper-2 px-1.5 py-0.5 text-[0.65rem] font-bold tracking-wide text-stone-600 uppercase">
                            <Lock className="size-3" aria-hidden /> Built-in
                          </span>
                        )}
                      </h3>
                      {role.description && <p className="mt-1 text-sm text-stone-600">{role.description}</p>}
                    </div>
                    {isSuper && !role.built_in && (
                      <div className="flex shrink-0 gap-1">
                        <Button
                          size="icon-sm"
                          variant="ghost"
                          aria-label={`Edit ${role.label}`}
                          onClick={() => setEditing({ mode: "edit", role })}
                        >
                          <Pencil />
                        </Button>
                        <Button
                          size="icon-sm"
                          variant="ghost"
                          className="text-destructive"
                          aria-label={`Delete ${role.label}`}
                          onClick={() => setDeleting(role)}
                        >
                          <Trash2 />
                        </Button>
                      </div>
                    )}
                  </div>
                  <div className="flex-1 px-5 py-3">
                    {role.permissions.length === 0 ? (
                      <p className="text-xs text-stone-500">No staff permissions.</p>
                    ) : (
                      <ul aria-label={`${role.label} permissions`} className="flex flex-wrap gap-1.5">
                        {role.permissions.map((key) => (
                          <li
                            key={key}
                            className="rounded-sm bg-navy-100 px-2 py-0.5 text-xs font-semibold text-navy-800"
                          >
                            {labels.get(key as StaffPermission) ?? key}
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                  <div className="border-t border-line px-5 py-2.5">
                    <Link
                      href={`/admin/users?role=${encodeURIComponent(role.name)}`}
                      className="inline-flex items-center gap-1.5 text-sm font-semibold text-navy-800 hover:underline"
                    >
                      <Users className="size-4" aria-hidden /> {people(role.users_count)}
                    </Link>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section aria-labelledby="permissions-heading">
          <h2 id="permissions-heading" className="mb-1 font-sans text-lg font-bold text-ink">
            Permissions
          </h2>
          <p className="mb-3 text-sm text-stone-500">
            What each permission lets staff do, and who has it — directly or through a role.
          </p>
          {permissions.isPending ? (
            <Skeleton className="h-64 w-full" />
          ) : permissions.isError ? (
            <FormAlert>Couldn&rsquo;t load permissions.</FormAlert>
          ) : (
            <div className="overflow-hidden rounded-md border border-line bg-white">
              <table className="w-full text-left text-sm">
                <thead className="border-b border-line bg-paper-2/60 text-xs font-semibold tracking-wide text-stone-500 uppercase">
                  <tr>
                    <th className="px-5 py-3">Permission</th>
                    <th className="px-5 py-3 text-right">Held by</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {permissions.data.map((p) => (
                    <tr key={p.key}>
                      <td className="px-5 py-3">
                        <p className="font-semibold text-ink">{p.label}</p>
                        <p className="text-xs text-stone-500">{p.description}</p>
                      </td>
                      <td className="px-5 py-3 text-right whitespace-nowrap">
                        <Button size="sm" variant="outline" onClick={() => setHoldersOf(p)}>
                          {people(p.users_count)}
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>

      {editing && (
        <RoleDialog
          key={editing.mode === "edit" ? editing.role.id : "new"}
          editing={editing}
          permissions={permissions.data ?? []}
          onClose={() => setEditing(null)}
        />
      )}
      <DeleteRoleDialog role={deleting} onClose={() => setDeleting(null)} />
      <HoldersSheet permission={holdersOf} onClose={() => setHoldersOf(null)} />
    </>
  );
}

function RoleDialog({
  editing,
  permissions,
  onClose,
}: {
  editing: Editing;
  permissions: PermissionInfo[];
  onClose: () => void;
}) {
  const role = editing.mode === "edit" ? editing.role : null;
  const save = useSaveRole();
  const [name, setName] = useState(role?.name ?? "");
  const [description, setDescription] = useState(role?.description ?? "");
  const [perms, setPerms] = useState<StaffPermission[]>((role?.permissions ?? []) as StaffPermission[]);
  const [error, setError] = useState<string | null>(null);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    save.mutate(
      { id: role?.id, name: name.trim(), description: description.trim() || null, permissions: perms },
      { onSuccess: onClose, onError: (err) => setError(errorText(err)) },
    );
  };

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>{role ? `Edit ${role.name}` : "New role"}</DialogTitle>
            <DialogDescription>
              {role && role.users_count > 0
                ? `Changes apply straight away to the ${people(role.users_count)} with this role.`
                : "Pick what people with this role can do. You give the role to people on their user page."}
            </DialogDescription>
          </DialogHeader>
          {error && <FormAlert>{error}</FormAlert>}
          <div className="space-y-1.5">
            <Label htmlFor="role-name">Name</Label>
            <Input
              id="role-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              maxLength={50}
              placeholder="e.g. Accountant"
              required
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="role-description">
              Description <span className="font-normal text-stone-500">(optional)</span>
            </Label>
            <Textarea
              id="role-description"
              rows={2}
              maxLength={300}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Who this role is for"
            />
          </div>
          <fieldset className="space-y-2.5">
            <legend className="mb-2 text-sm font-semibold text-ink">Permissions</legend>
            {permissions.map((p) => (
              <label key={p.key} className="flex items-start gap-3 text-sm">
                <input
                  type="checkbox"
                  checked={perms.includes(p.key)}
                  onChange={(e) => setPerms(e.target.checked ? [...perms, p.key] : perms.filter((x) => x !== p.key))}
                  className="mt-0.5 size-4 accent-[var(--navy-900)]"
                />
                <span>
                  <span className="block font-semibold text-ink">{p.label}</span>
                  <span className="block text-xs text-stone-500">{p.description}</span>
                </span>
              </label>
            ))}
          </fieldset>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" disabled={save.isPending || name.trim().length < 2}>
              {save.isPending ? "Saving…" : role ? "Save role" : "Create role"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function DeleteRoleDialog({ role, onClose }: { role: AdminRole | null; onClose: () => void }) {
  const remove = useDeleteRole();
  const [error, setError] = useState<string | null>(null);

  return (
    <Dialog
      open={!!role}
      onOpenChange={(open) => {
        if (!open) {
          setError(null);
          onClose();
        }
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete {role?.name}?</DialogTitle>
          <DialogDescription>
            {role && role.users_count > 0
              ? `The ${people(role.users_count)} with this role will lose its permissions straight away, unless another role or a direct permission gives them the same access.`
              : "Nobody has this role."}{" "}
            This is recorded in the audit log.
          </DialogDescription>
        </DialogHeader>
        {error && <FormAlert>{error}</FormAlert>}
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button
            variant="destructive"
            disabled={remove.isPending}
            onClick={() =>
              role &&
              remove.mutate(role.id, {
                onSuccess: onClose,
                onError: (e) => setError(errorText(e)),
              })
            }
          >
            {remove.isPending ? "Deleting…" : "Delete role"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function HoldersSheet({ permission, onClose }: { permission: PermissionInfo | null; onClose: () => void }) {
  const holders = usePermissionHolders(permission?.key ?? null);

  return (
    <Sheet open={!!permission} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-md">
        <SheetHeader className="border-b border-line">
          <SheetTitle>Who has &ldquo;{permission?.label}&rdquo;</SheetTitle>
          <SheetDescription>{permission?.description}</SheetDescription>
        </SheetHeader>
        <div className="px-4 pb-6">
          {holders.isPending ? (
            <Skeleton className="h-40 w-full" />
          ) : holders.isError ? (
            <FormAlert>Couldn&rsquo;t load this list.</FormAlert>
          ) : holders.data.length === 0 ? (
            <p className="py-6 text-sm text-stone-500">Nobody has this permission.</p>
          ) : (
            <ul className="divide-y divide-line">
              {holders.data.map((u) => (
                <li key={u.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 py-3">
                  <div className="min-w-0 flex-1">
                    <Link
                      href={`/admin/users/${u.id}`}
                      className="font-semibold text-ink hover:text-navy-800 hover:underline"
                    >
                      {u.name}
                    </Link>
                    <p className="truncate text-xs text-stone-500">{u.email}</p>
                  </div>
                  {u.status === "suspended" && <StatusPill status="suspended" />}
                  <p className="w-full text-xs text-stone-600">
                    Through: <span className="font-semibold">{u.via.join(", ")}</span>
                  </p>
                </li>
              ))}
            </ul>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
