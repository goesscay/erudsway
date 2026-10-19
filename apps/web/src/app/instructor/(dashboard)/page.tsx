"use client";

import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Plus } from "lucide-react";
import { Field, FormAlert, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { statusLabel, statusStyle, type InstructorCourse } from "@/lib/authoring/editor";
import { useMe } from "@/lib/auth/session";
import { formatDuration, formatPaise, plural } from "@/lib/format";
import { cn } from "@/lib/utils";

export default function InstructorCoursesPage() {
  const { data: me } = useMe();
  const [creating, setCreating] = useState(false);
  const courses = useQuery({
    queryKey: ["instructor-courses"],
    enabled: !!me?.instructor,
    queryFn: async () => (await unwrap(api.GET("/instructor/courses"))).data as InstructorCourse[],
  });

  return (
    <>
      <header className="flex flex-wrap items-end justify-between gap-4 border-b border-line bg-white px-8 py-6">
        <div>
          <h1 className="text-h2">Courses</h1>
          <p className="mt-1 text-sm text-stone-500">Build a course, submit it for review, and we&rsquo;ll publish it once approved.</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus /> New course
        </Button>
      </header>

      <div className="px-8 py-8">
        {courses.isPending ? (
          <div className="space-y-3">
            {[0, 1, 2].map((i) => (
              <Skeleton key={i} className="h-28 w-full" />
            ))}
          </div>
        ) : courses.isError ? (
          <FormAlert>Couldn&rsquo;t load your courses. Refresh to try again.</FormAlert>
        ) : courses.data.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center">
            <p className="font-serif text-2xl text-navy-900">Your first course starts here</p>
            <p className="mx-auto mt-2 max-w-md text-sm text-stone-700">
              Pick a topic you handle every week in practice — a return you file, an audit area you know well — and turn
              it into a course.
            </p>
            <Button className="mt-6" onClick={() => setCreating(true)}>
              <Plus /> Create a course
            </Button>
          </div>
        ) : (
          <ul className="space-y-3">
            {courses.data.map((c) => (
              <li key={c.id}>
                <Link
                  href={`/instructor/courses/${c.id}/curriculum`}
                  className="group grid grid-cols-[120px_1fr] items-center gap-5 rounded-md border border-line bg-white p-4 hover:border-input sm:grid-cols-[160px_1fr_auto]"
                >
                  <div className="relative aspect-video overflow-hidden rounded-sm bg-paper-2">
                    {c.thumbnail_url && <Image src={c.thumbnail_url} alt="" fill sizes="160px" className="object-cover" />}
                  </div>
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className={cn("inline-flex h-6 items-center rounded-sm px-2 text-[0.7rem] font-bold tracking-wide ring-1 ring-inset", statusStyle[c.status])}>
                        {statusLabel[c.status]}
                      </span>
                      <span className="text-xs text-stone-500">{c.category.name}</span>
                    </div>
                    <p className="mt-1.5 truncate font-semibold text-ink group-hover:text-navy-800">{c.title}</p>
                    <p className="mt-1 text-xs text-stone-500">
                      {plural(c.lecture_count, "lecture")} · {formatDuration(c.duration_seconds)}
                      {c.status === "published" && <> · {c.enrollment_count.toLocaleString("en-IN")} learners</>}
                    </p>
                  </div>
                  <span className="tabular hidden text-sm font-semibold text-stone-700 sm:block">
                    {c.price ? formatPaise(c.price.amount) : "Free"}
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </div>

      <NewCourseDialog open={creating} onClose={() => setCreating(false)} />
    </>
  );
}

function NewCourseDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const router = useRouter();
  const [title, setTitle] = useState("");
  const [category, setCategory] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const categories = useQuery({
    queryKey: ["categories"],
    enabled: open,
    queryFn: async () => (await unwrap(api.GET("/categories"))).data,
  });

  const create = async () => {
    setBusy(true);
    setError(null);
    try {
      const res = await mutate(() => api.POST("/instructor/courses", { body: { title: title.trim(), category } }));
      router.push(`/instructor/courses/${res.data.id}/learners`);
    } catch (e) {
      setError(e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.");
      setBusy(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Create a course</DialogTitle>
          <DialogDescription>You can change both of these later.</DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          {error && <FormAlert>{error}</FormAlert>}
          <Field label="Working title" hint="e.g. GSTR-9 Annual Return, Clause by Clause">
            {(p) => <TextInput {...p} value={title} maxLength={120} onChange={(e) => setTitle(e.target.value)} autoFocus />}
          </Field>
          <Field label="Subcategory">
            {(p) => (
              <select
                {...p}
                value={category}
                onChange={(e) => setCategory(e.target.value)}
                className="h-11 w-full rounded-md border border-input bg-white px-3 text-sm"
              >
                <option value="">Choose…</option>
                {categories.data?.map((c) => (
                  <optgroup key={c.slug} label={c.name}>
                    {c.children?.map((s) => (
                      <option key={s.slug} value={s.slug}>
                        {s.name}
                      </option>
                    ))}
                  </optgroup>
                ))}
              </select>
            )}
          </Field>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={create} disabled={busy || title.trim().length < 5 || !category}>
            {busy ? "Creating…" : "Create course"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
