"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";
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
import { Textarea } from "@/components/ui/textarea";
import { useCreateUser, useRoles, useUpdateUser } from "@/lib/admin/api";
import { api, ApiRequestError, mutate } from "@/lib/api/client";

type Person = { id: string; name: string; email: string };

function errorText(e: unknown) {
  return e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.";
}

export function SuspendDialog({ user, onClose }: { user: Person | null; onClose: () => void }) {
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
      await queryClient.invalidateQueries({ queryKey: ["admin", "user", user.id] });
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

export async function reactivateUser(id: string, queryClient: ReturnType<typeof useQueryClient>) {
  await mutate(() => api.POST("/admin/users/{user}/reactivate", { params: { path: { user: id } } }));
  await queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
  await queryClient.invalidateQueries({ queryKey: ["admin", "user", id] });
}

/** Create an account; the person gets an email to set their password. Admins can give custom roles too. */
export function AddUserDialog({
  open,
  canGiveRoles,
  onClose,
}: {
  open: boolean;
  canGiveRoles: boolean;
  onClose: () => void;
}) {
  const router = useRouter();
  const create = useCreateUser();
  const roles = useRoles();
  const custom = (roles.data ?? []).filter((r) => !r.built_in);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [picked, setPicked] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);

  const close = () => {
    setName("");
    setEmail("");
    setPicked([]);
    setError(null);
    onClose();
  };

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    create.mutate(
      { name: name.trim(), email: email.trim(), roles: picked },
      {
        onSuccess: (res) => {
          close();
          router.push(`/admin/users/${res.data.id}`);
        },
        onError: (err) => setError(errorText(err)),
      },
    );
  };

  return (
    <Dialog open={open} onOpenChange={(o) => !o && close()}>
      <DialogContent className="max-h-[90dvh] overflow-y-auto">
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>Add a user</DialogTitle>
            <DialogDescription>
              We&rsquo;ll email them a link to set their password. The link works for 3 days.
            </DialogDescription>
          </DialogHeader>
          {error && <FormAlert>{error}</FormAlert>}
          <div className="space-y-1.5">
            <Label htmlFor="new-user-name">Full name</Label>
            <Input id="new-user-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={80} required />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="new-user-email">Email</Label>
            <Input
              id="new-user-email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              maxLength={190}
              autoComplete="off"
              required
            />
          </div>
          {canGiveRoles && custom.length > 0 && (
            <fieldset className="space-y-2">
              <legend className="mb-1.5 text-sm font-semibold text-ink">
                Staff roles <span className="font-normal text-stone-500">(optional)</span>
              </legend>
              {custom.map((r) => (
                <label key={r.id} className="flex items-start gap-3 text-sm">
                  <input
                    type="checkbox"
                    checked={picked.includes(r.name)}
                    onChange={(e) =>
                      setPicked(e.target.checked ? [...picked, r.name] : picked.filter((x) => x !== r.name))
                    }
                    className="mt-0.5 size-4 accent-[var(--navy-900)]"
                  />
                  <span>
                    <span className="block font-semibold text-ink">{r.name}</span>
                    {r.description && <span className="block text-xs text-stone-500">{r.description}</span>}
                  </span>
                </label>
              ))}
            </fieldset>
          )}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={close}>
              Cancel
            </Button>
            <Button type="submit" disabled={create.isPending || name.trim().length < 2 || !email.includes("@")}>
              {create.isPending ? "Adding…" : "Add user and send email"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function EditDetailsDialog({ user, onClose }: { user: Person | null; onClose: () => void }) {
  return (
    <Dialog open={!!user} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>{user && <EditDetailsForm key={user.id} user={user} onClose={onClose} />}</DialogContent>
    </Dialog>
  );
}

function EditDetailsForm({ user, onClose }: { user: Person; onClose: () => void }) {
  const update = useUpdateUser(user.id);
  const [name, setName] = useState(user.name);
  const [email, setEmail] = useState(user.email);
  const [error, setError] = useState<string | null>(null);
  const emailChanged = email.trim().toLowerCase() !== user.email.toLowerCase();

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    update.mutate(
      { name: name.trim(), email: email.trim() },
      { onSuccess: onClose, onError: (err) => setError(errorText(err)) },
    );
  };

  return (
    <form onSubmit={submit} className="space-y-4">
      <DialogHeader>
        <DialogTitle>Edit details</DialogTitle>
        <DialogDescription>Changes are recorded in the audit log.</DialogDescription>
      </DialogHeader>
      {error && <FormAlert>{error}</FormAlert>}
      <div className="space-y-1.5">
        <Label htmlFor="edit-user-name">Full name</Label>
        <Input id="edit-user-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={80} required />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="edit-user-email">Email</Label>
        <Input
          id="edit-user-email"
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          maxLength={190}
          required
          aria-describedby={emailChanged ? "edit-user-email-note" : undefined}
        />
        {emailChanged && (
          <p id="edit-user-email-note" className="text-xs text-gold-600">
            They&rsquo;ll sign in with the new email, and we&rsquo;ll ask them to verify it.
          </p>
        )}
      </div>
      <DialogFooter>
        <Button type="button" variant="outline" onClick={onClose}>
          Cancel
        </Button>
        <Button type="submit" disabled={update.isPending || name.trim().length < 2}>
          {update.isPending ? "Saving…" : "Save"}
        </Button>
      </DialogFooter>
    </form>
  );
}
