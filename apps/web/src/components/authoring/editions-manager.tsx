"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from "lucide-react";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, unwrap } from "@/lib/api/client";
import { hasPermission, useMe } from "@/lib/auth/session";
import { useEditorAction, type CourseEditor } from "@/lib/authoring/editor";
import { formatPaise } from "@/lib/format";

type Edition = CourseEditor["editions"][number];

function errorText(e: unknown) {
  return e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Couldn’t save.";
}

/**
 * Editions: one course sold at several levels, entry level first, each including the ones
 * before it. Lectures and downloads are then held back to an edition from the curriculum.
 */
export function EditionsManager({ course, locked }: { course: CourseEditor; locked: boolean }) {
  const [editing, setEditing] = useState<Edition | "new" | null>(null);
  const [error, setError] = useState<string | null>(null);
  const editions = course.editions;

  const reorder = useEditorAction(course.id, (ids: number[]) =>
    unwrap(api.PUT("/instructor/courses/{course}/editions/order", { params: { path: { course: course.id } }, body: { ids } })),
  );
  const remove = useEditorAction(course.id, (id: number) => unwrap(api.DELETE("/instructor/editions/{edition}", { params: { path: { edition: id } } })));

  const move = (index: number, by: -1 | 1) => {
    const ids = editions.map((e) => e.id);
    [ids[index], ids[index + by]] = [ids[index + by], ids[index]];
    setError(null);
    reorder.mutate(ids, { onError: (e) => setError(errorText(e)) });
  };

  return (
    <section aria-labelledby="editions-title" className="mt-12 max-w-3xl">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 id="editions-title" className="text-h3">
            Editions
          </h2>
          <p className="mt-1 max-w-[60ch] text-sm text-stone-700">
            Sell the course at several levels — e.g. Video Course, then Course + Practical Kit. Each includes everything in the ones above it in
            this list. With editions, the course page shows a &ldquo;Choose your option&rdquo; picker and the listed price is the cheapest one.
          </p>
        </div>
        <Button type="button" variant="outline" onClick={() => setEditing("new")} disabled={locked || editions.length >= 6}>
          <Plus /> Add edition
        </Button>
      </div>
      {error && (
        <div className="mt-4">
          <FormAlert>{error}</FormAlert>
        </div>
      )}
      {editions.length === 0 ? (
        <p className="mt-5 rounded-md border border-dashed border-line bg-white px-5 py-6 text-sm text-stone-500">
          No editions — the course is sold at the single price above.
        </p>
      ) : (
        <ol className="mt-5 space-y-2">
          {editions.map((e, i) => (
            <li key={e.id} className="flex flex-wrap items-start gap-3 rounded-md border border-line bg-white p-4">
              <span className="tabular flex size-7 shrink-0 items-center justify-center rounded-full border border-gold-400 text-sm text-gold-600" aria-hidden>
                {i + 1}
              </span>
              <div className="min-w-0 flex-1">
                <p className="font-semibold text-ink">
                  {e.name} <span className="tabular font-normal text-stone-700">· {formatPaise(e.price)}</span>
                  {e.is_recommended && <span className="ml-2 text-xs font-bold text-gold-600 uppercase">Recommended</span>}
                </p>
                <p className="mt-0.5 text-xs text-stone-500">
                  {[
                    e.access_days ? `${e.access_days} days of access` : "No end date",
                    e.video_view_limit ? `${e.video_view_limit} full ${e.video_view_limit === 1 ? "view" : "views"} per video` : "Unlimited viewing",
                    e.grants_simulator && "GST simulator",
                    e.includes_doubt_session && "Doubt session",
                  ]
                    .filter(Boolean)
                    .join(" · ")}
                </p>
                {e.includes.length > 0 && <p className="mt-1 text-xs text-stone-700">{e.includes.join(" · ")}</p>}
              </div>
              <div className="flex items-center gap-0.5">
                <Button type="button" size="icon-sm" variant="ghost" aria-label={`Move ${e.name} up`} disabled={locked || i === 0} onClick={() => move(i, -1)}>
                  <ArrowUp />
                </Button>
                <Button
                  type="button"
                  size="icon-sm"
                  variant="ghost"
                  aria-label={`Move ${e.name} down`}
                  disabled={locked || i === editions.length - 1}
                  onClick={() => move(i, 1)}
                >
                  <ArrowDown />
                </Button>
                <Button type="button" size="sm" variant="ghost" disabled={locked} onClick={() => setEditing(e)}>
                  <Pencil /> Edit
                </Button>
                <Button
                  type="button"
                  size="icon-sm"
                  variant="ghost"
                  className="text-destructive"
                  aria-label={`Delete ${e.name}`}
                  disabled={locked}
                  onClick={() => {
                    setError(null);
                    remove.mutate(e.id, { onError: (err) => setError(errorText(err)) });
                  }}
                >
                  <Trash2 />
                </Button>
              </div>
            </li>
          ))}
        </ol>
      )}
      <EditionDialog course={course} editing={editing} onClose={() => setEditing(null)} />
    </section>
  );
}

function EditionDialog({ course, editing, onClose }: { course: CourseEditor; editing: Edition | "new" | null; onClose: () => void }) {
  return (
    <Dialog open={editing !== null} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-lg">
        {editing !== null && <EditionForm key={editing === "new" ? "new" : editing.id} course={course} edition={editing === "new" ? null : editing} onClose={onClose} />}
      </DialogContent>
    </Dialog>
  );
}

function EditionForm({ course, edition, onClose }: { course: CourseEditor; edition: Edition | null; onClose: () => void }) {
  const { data: me } = useMe();
  const staff = hasPermission(me, "manage_courses");
  const tiers = useQuery({ queryKey: ["price-tiers"], queryFn: async () => (await unwrap(api.GET("/instructor/price-tiers"))).data });
  const [name, setName] = useState(edition?.name ?? "");
  const [tier, setTier] = useState(edition ? String(edition.price_tier_id) : "");
  const [includes, setIncludes] = useState((edition?.includes ?? []).join("\n"));
  const [recommended, setRecommended] = useState(edition?.is_recommended ?? false);
  const [accessDays, setAccessDays] = useState(edition?.access_days ? String(edition.access_days) : "");
  const [viewLimit, setViewLimit] = useState(edition?.video_view_limit ? String(edition.video_view_limit) : "");
  const [simulator, setSimulator] = useState(edition?.grants_simulator ?? false);
  const [doubt, setDoubt] = useState(edition?.includes_doubt_session ?? false);
  const [error, setError] = useState<string | null>(null);

  const save = useEditorAction(course.id, () => {
    const body = {
      name: name.trim(),
      price_tier_id: Number(tier),
      includes: includes
        .split("\n")
        .map((l) => l.trim())
        .filter(Boolean),
      is_recommended: recommended,
      access_days: accessDays ? Number(accessDays) : null,
      video_view_limit: viewLimit ? Number(viewLimit) : null,
      ...(staff ? { grants_simulator: simulator, includes_doubt_session: doubt } : {}),
    };
    return edition
      ? unwrap(api.PATCH("/instructor/editions/{edition}", { params: { path: { edition: edition.id } }, body }))
      : unwrap(api.POST("/instructor/courses/{course}/editions", { params: { path: { course: course.id } }, body }));
  });

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        setError(null);
        save.mutate(undefined, { onSuccess: onClose, onError: (err) => setError(errorText(err)) });
      }}
    >
      <DialogHeader>
        <DialogTitle>{edition ? `Edit ${edition.name}` : "Add an edition"}</DialogTitle>
        <DialogDescription>Learners see the name, price and what it adds on the course page.</DialogDescription>
      </DialogHeader>
      {error && (
        <div className="mt-3">
          <FormAlert>{error}</FormAlert>
        </div>
      )}
      <div className="mt-4 space-y-4">
        <div className="grid gap-3 sm:grid-cols-[1fr_160px]">
          <div className="space-y-1.5">
            <Label htmlFor="ed-name">Name</Label>
            <Input id="ed-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={60} placeholder="Course + Practical Kit" />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="ed-price">Price</Label>
            <select id="ed-price" value={tier} onChange={(e) => setTier(e.target.value)} className="h-10 w-full rounded-md border border-input bg-white px-3 text-sm">
              <option value="">Choose…</option>
              {(tiers.data ?? []).map((t) => (
                <option key={t.id} value={t.id}>
                  {formatPaise(t.amount)}
                </option>
              ))}
            </select>
          </div>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="ed-includes">What it adds (one per line)</Label>
          <Textarea id="ed-includes" rows={4} value={includes} onChange={(e) => setIncludes(e.target.value)} placeholder={"Chapter-wise PDF notes\nFiling checklists"} />
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="ed-days">Access period (days)</Label>
            <Input id="ed-days" inputMode="numeric" value={accessDays} onChange={(e) => setAccessDays(e.target.value.replace(/\D/g, ""))} placeholder="Blank = no end date" />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="ed-views">Full views per video</Label>
            <Input id="ed-views" inputMode="numeric" value={viewLimit} onChange={(e) => setViewLimit(e.target.value.replace(/\D/g, ""))} placeholder="Blank = unlimited" />
          </div>
        </div>
        <label className="flex items-center gap-2.5 text-sm text-ink">
          <input type="checkbox" checked={recommended} onChange={(e) => setRecommended(e.target.checked)} className="size-4 accent-[var(--navy-900)]" />
          Recommended (pre-selected on the course page)
        </label>
        {staff && (
          <fieldset className="space-y-2 rounded-md border border-line bg-paper p-3">
            <legend className="px-1 text-xs font-semibold tracking-wide text-stone-500 uppercase">Erudsway services</legend>
            <label className="flex items-center gap-2.5 text-sm text-ink">
              <input type="checkbox" checked={simulator} onChange={(e) => setSimulator(e.target.checked)} className="size-4 accent-[var(--navy-900)]" />
              GST simulator access (login created automatically)
            </label>
            <label className="flex items-center gap-2.5 text-sm text-ink">
              <input type="checkbox" checked={doubt} onChange={(e) => setDoubt(e.target.checked)} className="size-4 accent-[var(--navy-900)]" />
              One 60-minute faculty doubt session
            </label>
          </fieldset>
        )}
      </div>
      <DialogFooter className="mt-5">
        <Button type="button" variant="outline" onClick={onClose}>
          Cancel
        </Button>
        <Button type="submit" disabled={save.isPending || name.trim().length < 2 || !tier}>
          {save.isPending ? "Saving…" : edition ? "Save" : "Add edition"}
        </Button>
      </DialogFooter>
    </form>
  );
}
