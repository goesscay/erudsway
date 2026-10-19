"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowDown, ArrowUp, Eye, EyeOff, Pencil, Plus, Trash2 } from "lucide-react";
import type { components } from "@erudsway/api-client";
import { AdminPageHeader } from "@/components/admin/page-header";
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
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { cn } from "@/lib/utils";

type Category = components["schemas"]["AdminCategoryResource"];

/** What the edit dialog is doing: a new area, a new subcategory under `parent`, or editing `category`. */
type Editing = { mode: "new"; parent: Category | null } | { mode: "edit"; category: Category };

const categoriesKey = ["admin", "categories"];

function errorText(e: unknown) {
  return e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.";
}

export default function AdminCategoriesPage() {
  return <RequireAuth permission="manage_categories">{() => <Categories />}</RequireAuth>;
}

function Categories() {
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState<Editing | null>(null);
  const [deleting, setDeleting] = useState<Category | null>(null);
  const [error, setError] = useState<string | null>(null);
  const list = useQuery({
    queryKey: categoriesKey,
    queryFn: async () => (await unwrap(api.GET("/admin/categories"))).data,
  });
  const areas = list.data ?? [];

  const refresh = () => queryClient.invalidateQueries({ queryKey: categoriesKey });
  const run = async (call: () => Promise<unknown>) => {
    setError(null);
    try {
      await call();
    } catch (e) {
      setError(errorText(e));
    }
    await refresh();
  };

  const move = (siblings: Category[], index: number, by: -1 | 1, parentId: number | null) => {
    const ids = siblings.map((s) => s.id);
    [ids[index], ids[index + by]] = [ids[index + by], ids[index]];
    void run(() => mutate(() => api.PUT("/admin/categories/order", { body: { parent_id: parentId, ids } })));
  };
  const toggle = (c: Category) =>
    void run(() =>
      mutate(() =>
        api.PATCH("/admin/categories/{category}", {
          params: { path: { category: c.id } },
          body: { is_active: !c.is_active },
        }),
      ),
    );

  return (
    <>
      <AdminPageHeader
        title="Categories"
        description="The areas and subcategories learners browse. Courses always sit in a subcategory. Changes reach the public site within a few minutes."
      >
        <Button onClick={() => setEditing({ mode: "new", parent: null })}>
          <Plus /> New area
        </Button>
      </AdminPageHeader>
      <div className="px-8 py-6">
        {error && (
          <div className="mb-4">
            <FormAlert>{error}</FormAlert>
          </div>
        )}
        {list.isPending ? (
          <Skeleton className="h-96 w-full" />
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load categories.</FormAlert>
        ) : areas.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center text-sm text-stone-500">
            No categories yet.
          </div>
        ) : (
          <ol className="space-y-4">
            {areas.map((area, i) => (
              <li key={area.id} className="overflow-hidden rounded-md border border-line bg-white">
                <Row
                  category={area}
                  first={i === 0}
                  last={i === areas.length - 1}
                  onMove={(by) => move(areas, i, by, null)}
                  onEdit={() => setEditing({ mode: "edit", category: area })}
                  onToggle={() => toggle(area)}
                  onDelete={() => setDeleting(area)}
                />
                <ol className="divide-y divide-line border-t border-line bg-paper/40">
                  {area.children.map((sub, j) => (
                    <li key={sub.id}>
                      <Row
                        category={sub}
                        nested
                        hiddenByParent={!area.is_active}
                        first={j === 0}
                        last={j === area.children.length - 1}
                        onMove={(by) => move(area.children, j, by, area.id)}
                        onEdit={() => setEditing({ mode: "edit", category: sub })}
                        onToggle={() => toggle(sub)}
                        onDelete={() => setDeleting(sub)}
                      />
                    </li>
                  ))}
                  <li className="px-5 py-2 pl-12">
                    <Button size="sm" variant="ghost" onClick={() => setEditing({ mode: "new", parent: area })}>
                      <Plus /> Add subcategory
                    </Button>
                  </li>
                </ol>
              </li>
            ))}
          </ol>
        )}
      </div>

      <EditDialog editing={editing} areas={areas} onClose={() => setEditing(null)} onSaved={refresh} />
      <DeleteDialog category={deleting} onClose={() => setDeleting(null)} onDeleted={refresh} />
    </>
  );
}

function Row({
  category: c,
  nested = false,
  hiddenByParent = false,
  first,
  last,
  onMove,
  onEdit,
  onToggle,
  onDelete,
}: {
  category: Category;
  nested?: boolean;
  hiddenByParent?: boolean;
  first: boolean;
  last: boolean;
  onMove: (by: -1 | 1) => void;
  onEdit: () => void;
  onToggle: () => void;
  onDelete: () => void;
}) {
  const visible = c.is_active && !hiddenByParent;
  const total = nested ? c.course_count : c.children.reduce((n, s) => n + s.course_count, 0);
  const live = nested ? c.published_count : c.children.reduce((n, s) => n + s.published_count, 0);

  return (
    <div className={cn("flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3", nested && "pl-12")}>
      <div className="min-w-0 flex-1">
        <p
          className={cn(
            "font-semibold",
            nested ? "text-sm text-ink" : "text-base text-navy-900",
            !visible && "text-stone-500",
          )}
        >
          {c.name}
          {!c.is_active && (
            <span className="ml-2 inline-flex h-5 items-center rounded-sm bg-paper-2 px-1.5 align-middle text-[0.65rem] font-bold tracking-wide text-stone-500 uppercase ring-1 ring-line ring-inset">
              Hidden
            </span>
          )}
        </p>
        <p className="text-xs text-stone-500">
          {visible ? (
            <Link href={`/courses/${c.slug}`} target="_blank" className="hover:underline">
              /courses/{c.slug}
            </Link>
          ) : (
            <>/courses/{c.slug}</>
          )}
          {" · "}
          {total === 0 ? "No courses" : `${total} ${total === 1 ? "course" : "courses"}, ${live} live`}
        </p>
      </div>
      <div className="flex items-center gap-0.5">
        <Button
          size="icon-sm"
          variant="ghost"
          aria-label={`Move ${c.name} up`}
          disabled={first}
          onClick={() => onMove(-1)}
        >
          <ArrowUp />
        </Button>
        <Button
          size="icon-sm"
          variant="ghost"
          aria-label={`Move ${c.name} down`}
          disabled={last}
          onClick={() => onMove(1)}
        >
          <ArrowDown />
        </Button>
        <Button size="sm" variant="ghost" onClick={onEdit}>
          <Pencil /> Edit
        </Button>
        <Button size="sm" variant="ghost" onClick={onToggle}>
          {c.is_active ? <EyeOff /> : <Eye />} {c.is_active ? "Hide" : "Show"}
        </Button>
        <Button
          size="icon-sm"
          variant="ghost"
          className="text-destructive"
          aria-label={`Delete ${c.name}`}
          onClick={onDelete}
        >
          <Trash2 />
        </Button>
      </div>
    </div>
  );
}

function EditDialog({
  editing,
  areas,
  onClose,
  onSaved,
}: {
  editing: Editing | null;
  areas: Category[];
  onClose: () => void;
  onSaved: () => Promise<unknown>;
}) {
  const current = editing?.mode === "edit" ? editing.category : null;
  const key = editing
    ? current
      ? `edit-${current.id}`
      : `new-${editing.mode === "new" ? (editing.parent?.id ?? "area") : ""}`
    : "closed";

  return (
    <Dialog open={!!editing} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-md">
        {editing && <EditForm key={key} editing={editing} areas={areas} onClose={onClose} onSaved={onSaved} />}
      </DialogContent>
    </Dialog>
  );
}

function EditForm({
  editing,
  areas,
  onClose,
  onSaved,
}: {
  editing: Editing;
  areas: Category[];
  onClose: () => void;
  onSaved: () => Promise<unknown>;
}) {
  const current = editing.mode === "edit" ? editing.category : null;
  const parentId = editing.mode === "edit" ? editing.category.parent_id : (editing.parent?.id ?? null);
  const isArea = parentId === null;
  const [name, setName] = useState(current?.name ?? "");
  const [slug, setSlug] = useState(current?.slug ?? "");
  const [description, setDescription] = useState(current?.description ?? "");
  const [parent, setParent] = useState<number | null>(parentId);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const parentName = areas.find((a) => a.id === parentId)?.name;
  const title = current ? `Edit ${current.name}` : isArea ? "New area" : `New subcategory in ${parentName}`;

  const submit = async () => {
    setBusy(true);
    setError(null);
    try {
      const body = {
        name: name.trim(),
        description: description.trim() || null,
        ...(slug.trim() && { slug: slug.trim() }),
      };
      if (current) {
        await mutate(() =>
          api.PATCH("/admin/categories/{category}", {
            params: { path: { category: current.id } },
            body: { ...body, ...(!isArea && { parent_id: parent }) },
          }),
        );
      } else {
        await mutate(() => api.POST("/admin/categories", { body: { ...body, parent_id: parentId } }));
      }
      await onSaved();
      onClose();
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>{title}</DialogTitle>
        <DialogDescription>
          {isArea
            ? "Areas are the top level of the catalogue menu."
            : "Instructors file their courses under subcategories."}
        </DialogDescription>
      </DialogHeader>
      {error && <FormAlert>{error}</FormAlert>}
      <div className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="cat-name">Name</Label>
          <Input id="cat-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={80} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="cat-slug">Web address</Label>
          <div className="flex items-center rounded-md border border-input bg-white pl-3 text-sm focus-within:border-navy-900">
            <span className="text-stone-500">/courses/</span>
            <input
              id="cat-slug"
              value={slug}
              onChange={(e) => setSlug(e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ""))}
              placeholder={current ? undefined : "made from the name"}
              className="h-10 min-w-0 flex-1 bg-transparent pr-3 focus:outline-none"
            />
          </div>
          {current && slug !== current.slug && (
            <p className="text-xs text-gold-600">
              Changing the address breaks links and search results that point to the old one.
            </p>
          )}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="cat-desc">Description (optional)</Label>
          <Textarea
            id="cat-desc"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={2}
            maxLength={300}
          />
        </div>
        {current && !isArea && (
          <div className="space-y-1.5">
            <Label htmlFor="cat-parent">Area</Label>
            <select
              id="cat-parent"
              value={parent ?? ""}
              onChange={(e) => setParent(Number(e.target.value))}
              className="h-10 w-full rounded-md border border-input bg-white px-3 text-sm"
            >
              {areas.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.name}
                </option>
              ))}
            </select>
          </div>
        )}
      </div>
      <DialogFooter>
        <Button variant="outline" onClick={onClose}>
          Cancel
        </Button>
        <Button onClick={submit} disabled={busy || name.trim().length < 2}>
          {busy ? "Saving…" : current ? "Save" : "Create"}
        </Button>
      </DialogFooter>
    </>
  );
}

function DeleteDialog({
  category,
  onClose,
  onDeleted,
}: {
  category: Category | null;
  onClose: () => void;
  onDeleted: () => Promise<unknown>;
}) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const blocked = !!category && (category.course_count > 0 || category.children.length > 0);

  const close = () => {
    setError(null);
    onClose();
  };
  const submit = async () => {
    if (!category) return;
    setBusy(true);
    setError(null);
    try {
      await mutate(() => api.DELETE("/admin/categories/{category}", { params: { path: { category: category.id } } }));
      await onDeleted();
      close();
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={!!category} onOpenChange={(o) => !o && close()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Delete {category?.name}?</DialogTitle>
          <DialogDescription>
            {blocked
              ? category && category.children.length > 0
                ? "This area still has subcategories. Delete or move them first — or hide the area instead."
                : "This category still has courses, so it can’t be deleted. Hide it instead, or move its courses to another category from the builder."
              : "It’s empty, so nothing else changes. This can’t be undone."}
          </DialogDescription>
        </DialogHeader>
        {error && <FormAlert>{error}</FormAlert>}
        <DialogFooter>
          <Button variant="outline" onClick={close}>
            {blocked ? "Close" : "Cancel"}
          </Button>
          {!blocked && (
            <Button variant="destructive" onClick={submit} disabled={busy}>
              {busy ? "Deleting…" : "Delete"}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
