"use client";

import Image from "next/image";
import Link from "next/link";
import { useDeferredValue, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Archive,
  ArchiveRestore,
  ExternalLink,
  MoreHorizontal,
  PencilRuler,
  Pin,
  PinOff,
  Rocket,
  Search,
  UserRoundPen,
  EyeOff,
  Plus,
} from "lucide-react";
import type { components, paths } from "@erudsway/api-client";
import { AdminPageHeader, formatDate } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { statusLabel, statusStyle } from "@/lib/authoring/editor";
import { formatCount, formatPaise } from "@/lib/format";
import { cn } from "@/lib/utils";

type Course = components["schemas"]["AdminCourseResource"];
type Query = NonNullable<paths["/admin/courses"]["get"]["parameters"]["query"]>;
type Status = NonNullable<Query["status"]>;
type Sort = NonNullable<Query["sort"]>;

const statuses: { value: Status | ""; label: string }[] = [
  { value: "", label: "All statuses" },
  { value: "published", label: "Live" },
  { value: "in_review", label: "In review" },
  { value: "draft", label: "Draft" },
  { value: "changes_requested", label: "Changes requested" },
  { value: "unpublished", label: "Unpublished" },
  { value: "archived", label: "Archived" },
];

const sorts: { value: Sort; label: string }[] = [
  { value: "updated", label: "Recently edited" },
  { value: "newest", label: "Newest" },
  { value: "enrollments", label: "Most enrolled" },
  { value: "title", label: "Title A–Z" },
];

/** Which lever a dialog is pulling. */
type Action = { kind: "publish" | "unpublish" | "archive" | "instructor"; course: Course };

const coursesKey = ["admin", "courses"];

export default function AdminCoursesPage() {
  return <RequireAuth permission="manage_courses">{() => <Courses />}</RequireAuth>;
}

function Courses() {
  const queryClient = useQueryClient();
  const [q, setQ] = useState("");
  const [status, setStatus] = useState<Status | "">("");
  const [category, setCategory] = useState("");
  const [featuredOnly, setFeaturedOnly] = useState(false);
  const [sort, setSort] = useState<Sort>("updated");
  const [page, setPage] = useState(1);
  const [action, setAction] = useState<Action | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const deferredQ = useDeferredValue(q.trim());

  const categories = useQuery({
    queryKey: ["catalog", "categories"],
    queryFn: async () => (await unwrap(api.GET("/categories"))).data,
  });
  const list = useQuery({
    queryKey: [...coursesKey, deferredQ, status, category, featuredOnly, sort, page],
    queryFn: () =>
      unwrap(
        api.GET("/admin/courses", {
          params: {
            query: {
              ...(deferredQ && { q: deferredQ }),
              ...(status && { status }),
              ...(category && { category }),
              ...(featuredOnly && { featured: true }),
              sort,
              page,
            },
          },
        }),
      ),
    placeholderData: (prev) => prev,
  });
  const rows = list.data?.data ?? [];
  const meta = (list.data as { meta?: { total: number; last_page: number } } | undefined)?.meta;

  const refresh = () => queryClient.invalidateQueries({ queryKey: coursesKey });
  const run = async (call: () => Promise<unknown>, done: string) => {
    setNotice(null);
    try {
      await call();
      setNotice(done);
      await refresh();
    } catch (e) {
      setNotice(e instanceof ApiRequestError ? e.message : "Something went wrong.");
    }
  };
  const feature = (c: Course, featured: boolean) =>
    run(
      () =>
        mutate(() =>
          api.PUT("/admin/courses/{course}/featured", { params: { path: { course: c.id } }, body: { featured } }),
        ),
      featured ? `“${c.title}” is now featured.` : `“${c.title}” is no longer featured.`,
    );
  const restore = (c: Course) =>
    run(
      () => mutate(() => api.POST("/admin/courses/{course}/restore", { params: { path: { course: c.id } } })),
      `“${c.title}” is restored.`,
    );
  const reset = () => setPage(1);

  return (
    <>
      <AdminPageHeader
        title="Courses"
        description={
          meta
            ? `${formatCount(meta.total)} ${meta.total === 1 ? "course" : "courses"} — every state, every instructor`
            : undefined
        }
      >
        <Link href="/instructor" className={buttonVariants({ variant: "outline" })}>
          <Plus /> New course
        </Link>
      </AdminPageHeader>
      <div className="px-8 py-6">
        <div className="mb-5 flex flex-wrap items-center gap-3">
          <div className="relative w-full max-w-sm">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-500" />
            <input
              type="search"
              value={q}
              onChange={(e) => {
                setQ(e.target.value);
                reset();
              }}
              placeholder="Title, address, instructor or email"
              aria-label="Search courses"
              className="h-10 w-full rounded-md border border-input bg-white pr-3 pl-9 text-sm focus:border-navy-900 focus:outline-none"
            />
          </div>
          <select
            aria-label="Filter by status"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value as Status | "");
              reset();
            }}
            className="h-10 rounded-md border border-input bg-white px-3 text-sm"
          >
            {statuses.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </select>
          <select
            aria-label="Filter by category"
            value={category}
            onChange={(e) => {
              setCategory(e.target.value);
              reset();
            }}
            className="h-10 max-w-56 rounded-md border border-input bg-white px-3 text-sm"
          >
            <option value="">All categories</option>
            {(categories.data ?? []).map((area) => (
              <optgroup key={area.slug} label={area.name}>
                <option value={area.slug}>All of {area.name}</option>
                {(area.children ?? []).map((sub) => (
                  <option key={sub.slug} value={sub.slug}>
                    {sub.name}
                  </option>
                ))}
              </optgroup>
            ))}
          </select>
          <select
            aria-label="Sort"
            value={sort}
            onChange={(e) => setSort(e.target.value as Sort)}
            className="h-10 rounded-md border border-input bg-white px-3 text-sm"
          >
            {sorts.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </select>
          <label className="flex items-center gap-2 text-sm text-stone-700">
            <input
              type="checkbox"
              checked={featuredOnly}
              onChange={(e) => {
                setFeaturedOnly(e.target.checked);
                reset();
              }}
              className="size-4 accent-navy-900"
            />
            Featured only
          </label>
        </div>

        {notice && (
          <p role="status" className="mb-4 rounded-md border border-line bg-white px-4 py-3 text-sm text-stone-700">
            {notice}
          </p>
        )}

        {list.isPending ? (
          <Skeleton className="h-64 w-full" />
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load courses.</FormAlert>
        ) : (
          <div className="overflow-x-auto rounded-md border border-line bg-white">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-line bg-paper-2/60 text-xs font-semibold tracking-wide text-stone-500 uppercase">
                <tr>
                  <th className="px-5 py-3">Course</th>
                  <th className="px-5 py-3">Instructor</th>
                  <th className="px-5 py-3">Status</th>
                  <th className="hidden px-5 py-3 lg:table-cell">Price</th>
                  <th className="hidden px-5 py-3 lg:table-cell">Learners</th>
                  <th className="hidden px-5 py-3 md:table-cell">Edited</th>
                  <th className="px-5 py-3">
                    <span className="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {rows.length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-5 py-12 text-center text-stone-500">
                      No courses match.
                    </td>
                  </tr>
                )}
                {rows.map((c) => (
                  <tr key={c.id} className="hover:bg-paper">
                    <td className="px-5 py-3">
                      <div className="flex items-center gap-3">
                        <div className="relative hidden h-10 w-[72px] shrink-0 overflow-hidden rounded-sm bg-paper-2 sm:block">
                          {c.thumbnail_url && (
                            <Image src={c.thumbnail_url} alt="" fill sizes="72px" className="object-cover" />
                          )}
                        </div>
                        <div className="min-w-0">
                          <Link
                            href={`/instructor/courses/${c.id}`}
                            className="line-clamp-1 font-semibold text-ink hover:text-navy-800 hover:underline"
                          >
                            {c.title}
                          </Link>
                          <p className="text-xs text-stone-500">
                            {c.category.parent ? `${c.category.parent} › ` : ""}
                            {c.category.name}
                            {c.featured && <span className="ml-1.5 font-semibold text-gold-600">· Featured</span>}
                          </p>
                        </div>
                      </div>
                    </td>
                    <td className="px-5 py-3">
                      <p className="text-ink">{c.instructor.name}</p>
                      <p className="text-xs text-stone-500">{c.instructor.email}</p>
                    </td>
                    <td className="px-5 py-3">
                      <span
                        className={cn(
                          "inline-flex h-6 items-center rounded-sm px-2 text-[0.7rem] font-bold tracking-wide whitespace-nowrap ring-1 ring-inset",
                          statusStyle[c.status],
                        )}
                      >
                        {statusLabel[c.status]}
                      </span>
                    </td>
                    <td className="tabular hidden px-5 py-3 text-stone-700 lg:table-cell">
                      {c.price ? formatPaise(c.price.amount) : "Free"}
                    </td>
                    <td className="tabular hidden px-5 py-3 text-stone-700 lg:table-cell">
                      {formatCount(c.enrollment_count)}
                    </td>
                    <td className="tabular hidden px-5 py-3 whitespace-nowrap text-stone-700 md:table-cell">
                      {formatDate(c.updated_at)}
                    </td>
                    <td className="px-5 py-3 text-right">
                      <RowMenu course={c} onAction={setAction} onFeature={feature} onRestore={restore} />
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

      <ReasonDialog
        action={action?.kind === "instructor" ? null : action}
        onClose={() => setAction(null)}
        onDone={(msg) => {
          setNotice(msg);
          void refresh();
        }}
      />
      <InstructorDialog
        course={action?.kind === "instructor" ? action.course : null}
        onClose={() => setAction(null)}
        onDone={(msg) => {
          setNotice(msg);
          void refresh();
        }}
      />
    </>
  );
}

function RowMenu({
  course: c,
  onAction,
  onFeature,
  onRestore,
}: {
  course: Course;
  onAction: (a: Action) => void;
  onFeature: (c: Course, featured: boolean) => void;
  onRestore: (c: Course) => void;
}) {
  const canPublish = ["draft", "changes_requested", "unpublished"].includes(c.status);
  const live = c.status === "published";

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label={`Actions for ${c.title}`}
        className={buttonVariants({ size: "icon", variant: "ghost" })}
      >
        <MoreHorizontal />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56">
        {c.status !== "archived" && (
          <DropdownMenuItem render={<Link href={`/instructor/courses/${c.id}`} />}>
            <PencilRuler /> Edit in builder
          </DropdownMenuItem>
        )}
        {live && (
          <DropdownMenuItem render={<Link href={`/course/${c.slug}`} target="_blank" />}>
            <ExternalLink /> View live page
          </DropdownMenuItem>
        )}
        <DropdownMenuSeparator />
        {canPublish && (
          <DropdownMenuItem onClick={() => onAction({ kind: "publish", course: c })}>
            <Rocket /> Publish now
          </DropdownMenuItem>
        )}
        {live && (
          <DropdownMenuItem onClick={() => onFeature(c, !c.featured)}>
            {c.featured ? <PinOff /> : <Pin />} {c.featured ? "Stop featuring" : "Feature on homepage"}
          </DropdownMenuItem>
        )}
        {live && (
          <DropdownMenuItem onClick={() => onAction({ kind: "unpublish", course: c })}>
            <EyeOff /> Unpublish
          </DropdownMenuItem>
        )}
        <DropdownMenuItem onClick={() => onAction({ kind: "instructor", course: c })}>
          <UserRoundPen /> Change instructor
        </DropdownMenuItem>
        {c.status === "archived" ? (
          <DropdownMenuItem onClick={() => onRestore(c)}>
            <ArchiveRestore /> Restore
          </DropdownMenuItem>
        ) : (
          c.status !== "in_review" && (
            <DropdownMenuItem variant="destructive" onClick={() => onAction({ kind: "archive", course: c })}>
              <Archive /> Archive
            </DropdownMenuItem>
          )
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

const reasonCopy = {
  publish: {
    title: "Publish without review?",
    body: "The course goes live straight away. It must still pass the instructor checklist. Add a note for the audit log if you like.",
    label: "Note (optional)",
    button: "Publish now",
    min: 0,
    done: "is live.",
  },
  unpublish: {
    title: "Unpublish this course?",
    body: "It disappears from the catalogue and can’t be bought. Enrolled learners keep access. The instructor can resubmit it.",
    label: "Reason",
    button: "Unpublish",
    min: 10,
    done: "is unpublished.",
  },
  archive: {
    title: "Archive this course?",
    body: "It leaves the catalogue for good and can no longer be edited. Enrolled learners keep access. You can restore it later.",
    label: "Reason",
    button: "Archive",
    min: 10,
    done: "is archived.",
  },
} as const;

function ReasonDialog({
  action,
  onClose,
  onDone,
}: {
  action: Action | null;
  onClose: () => void;
  onDone: (msg: string) => void;
}) {
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const kind = action?.kind === "instructor" ? null : action?.kind;
  const copy = kind ? reasonCopy[kind] : null;

  const close = () => {
    setReason("");
    setError(null);
    onClose();
  };

  const submit = async () => {
    if (!action || !kind) return;
    setBusy(true);
    setError(null);
    const path = { params: { path: { course: action.course.id } } };
    try {
      if (kind === "publish") {
        await mutate(() =>
          api.POST("/admin/courses/{course}/publish", { ...path, body: { reason: reason.trim() || null } }),
        );
      } else if (kind === "unpublish") {
        await mutate(() => api.POST("/admin/courses/{course}/unpublish", { ...path, body: { reason: reason.trim() } }));
      } else {
        await mutate(() => api.POST("/admin/courses/{course}/archive", { ...path, body: { reason: reason.trim() } }));
      }
      onDone(`“${action.course.title}” ${reasonCopy[kind].done}`);
      close();
    } catch (e) {
      setError(e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={!!copy} onOpenChange={(o) => !o && close()}>
      <DialogContent className="sm:max-w-md">
        {copy && action && (
          <>
            <DialogHeader>
              <DialogTitle>{copy.title}</DialogTitle>
              <DialogDescription>
                <span className="font-semibold text-ink">{action.course.title}</span> — {copy.body}
              </DialogDescription>
            </DialogHeader>
            {error && <FormAlert>{error}</FormAlert>}
            <div className="space-y-1.5">
              <Label htmlFor="course-reason">{copy.label}</Label>
              <Textarea id="course-reason" value={reason} onChange={(e) => setReason(e.target.value)} rows={3} />
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={close}>
                Cancel
              </Button>
              <Button
                variant={kind === "publish" ? "default" : "destructive"}
                onClick={submit}
                disabled={busy || reason.trim().length < copy.min}
              >
                {busy ? "Working…" : copy.button}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

function InstructorDialog({
  course,
  onClose,
  onDone,
}: {
  course: Course | null;
  onClose: () => void;
  onDone: (msg: string) => void;
}) {
  const [email, setEmail] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const close = () => {
    setEmail("");
    setError(null);
    onClose();
  };

  const submit = async () => {
    if (!course) return;
    setBusy(true);
    setError(null);
    try {
      const res = await mutate(() =>
        api.PUT("/admin/courses/{course}/instructor", {
          params: { path: { course: course.id } },
          body: { email: email.trim() },
        }),
      );
      onDone(`“${course.title}” now belongs to ${res.data.instructor.name}.`);
      close();
    } catch (e) {
      setError(e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={!!course} onOpenChange={(o) => !o && close()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Change instructor</DialogTitle>
          <DialogDescription>
            {course && (
              <>
                <span className="font-semibold text-ink">{course.title}</span> belongs to {course.instructor.name}.
                Sales already made stay credited to them; new sales, Q&amp;A and announcements go to the new instructor.
              </>
            )}
          </DialogDescription>
        </DialogHeader>
        {error && <FormAlert>{error}</FormAlert>}
        <div className="space-y-1.5">
          <Label htmlFor="new-instructor">New instructor&rsquo;s email</Label>
          <Input
            id="new-instructor"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="name@example.com"
          />
          <p className="text-xs text-stone-500">They must already be an approved instructor.</p>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={close}>
            Cancel
          </Button>
          <Button onClick={submit} disabled={busy || !email.includes("@")}>
            {busy ? "Moving…" : "Change instructor"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
