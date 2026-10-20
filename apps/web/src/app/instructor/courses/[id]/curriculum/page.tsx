"use client";

import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import {
  closestCenter,
  DndContext,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type Announcements,
  type DragEndEvent,
  type UniqueIdentifier,
} from "@dnd-kit/core";
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { CheckCircle2, ChevronDown, CircleAlert, ClipboardList, FileText, GripVertical, ListChecks, Loader2, MonitorPlay, Pencil, Plus, Trash2 } from "lucide-react";
import { AssignmentPanel, QuizPanel } from "@/components/authoring/assessment-panels";
import { ArticlePanel, ResourcesPanel, VideoPanel } from "@/components/authoring/lecture-panels";
import { FormAlert, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { api, ApiRequestError, ensureCsrfCookie, unwrap } from "@/lib/api/client";
import { editorKey, useEditorAction, type CourseEditor, type EditorLecture, type EditorSection } from "@/lib/authoring/editor";
import { formatClock, formatDuration, plural } from "@/lib/format";
import { cn } from "@/lib/utils";
import { StepHeader, StepSkeleton, useEditableCourse } from "../builder-shell";

export default function CurriculumStep() {
  const { course, locked } = useEditableCourse();
  if (!course) return <StepSkeleton />;
  return <CurriculumEditor course={course} locked={locked} />;
}

type Order = { id: number; lectures: number[] }[];

/** Screen-reader announcements that name the item rather than its internal id. */
function announcements(label: (id: UniqueIdentifier) => string): Announcements {
  return {
    onDragStart: ({ active }) => `Picked up ${label(active.id)}. Use the arrow keys to move it, space to drop, escape to cancel.`,
    onDragOver: ({ active, over }) => (over ? `${label(active.id)} is now in the place of ${label(over.id)}.` : `${label(active.id)} is not over a position.`),
    onDragEnd: ({ active, over }) => (over && over.id !== active.id ? `Moved ${label(active.id)} to the place of ${label(over.id)}.` : `${label(active.id)} was dropped in its original place.`),
    onDragCancel: ({ active }) => `Moving ${label(active.id)} was cancelled.`,
  };
}

function CurriculumEditor({ course, locked }: { course: CourseEditor; locked: boolean }) {
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));

  const addSection = useEditorAction(course.id, (title: string) =>
    unwrap(api.POST("/instructor/courses/{course}/sections", { params: { path: { course: course.id } }, body: { title } })),
  );

  /** Optimistically apply a new order, then persist; roll back by refetching on failure. */
  const applyOrder = async (order: Order) => {
    const byId = new Map(course.curriculum.flatMap((s) => s.lectures).map((l) => [l.id, l]));
    const sections = new Map(course.curriculum.map((s) => [s.id, s]));
    queryClient.setQueryData<CourseEditor>(editorKey(course.id), {
      ...course,
      curriculum: order.map((o, i) => ({ ...sections.get(o.id)!, position: i + 1, lectures: o.lectures.map((id, j) => ({ ...byId.get(id)!, position: j + 1 })) })),
    });
    try {
      await ensureCsrfCookie();
      const res = await unwrap(api.PUT("/instructor/courses/{course}/curriculum/order", { params: { path: { course: course.id } }, body: { sections: order } }));
      queryClient.setQueryData(editorKey(course.id), res.data);
    } catch (e) {
      setError(e instanceof ApiRequestError ? e.message : "Couldn’t save the new order.");
      await queryClient.invalidateQueries({ queryKey: editorKey(course.id) });
    }
  };

  const currentOrder = (): Order => course.curriculum.map((s) => ({ id: s.id, lectures: s.lectures.map((l) => l.id) }));

  const onSectionDragEnd = ({ active, over }: DragEndEvent) => {
    if (!over || active.id === over.id) return;
    const order = currentOrder();
    const from = order.findIndex((s) => `s-${s.id}` === active.id);
    const to = order.findIndex((s) => `s-${s.id}` === over.id);
    if (from >= 0 && to >= 0) void applyOrder(arrayMove(order, from, to));
  };

  const moveLecture = (lectureId: number, toSectionId: number) => {
    const order = currentOrder().map((s) => ({ ...s, lectures: s.lectures.filter((id) => id !== lectureId) }));
    order.find((s) => s.id === toSectionId)?.lectures.push(lectureId);
    void applyOrder(order);
  };

  const reorderWithin = (sectionId: number, from: number, to: number) => {
    void applyOrder(currentOrder().map((s) => (s.id === sectionId ? { ...s, lectures: arrayMove(s.lectures, from, to) } : s)));
  };

  const totals = course.curriculum.flatMap((s) => s.lectures);

  return (
    <div>
      <StepHeader title="Curriculum">
        Organise lectures into sections. Drag the handles (or focus a handle and use the arrow keys) to reorder. Videos are
        processed into HD and mobile versions after upload — you can keep working meanwhile.
      </StepHeader>

      <p className="mb-5 text-sm text-stone-700">
        {plural(course.curriculum.length, "section")} · {plural(totals.length, "lecture")} · {formatDuration(totals.reduce((n, l) => n + l.duration_seconds, 0))}
      </p>

      {error && (
        <div className="mb-4">
          <FormAlert>{error}</FormAlert>
        </div>
      )}

      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        onDragEnd={onSectionDragEnd}
        accessibility={{ announcements: announcements((id) => `section "${course.curriculum.find((s) => `s-${s.id}` === id)?.title ?? ""}"`) }}
      >
        <SortableContext items={course.curriculum.map((s) => `s-${s.id}`)} strategy={verticalListSortingStrategy}>
          <ol className="space-y-5">
            {course.curriculum.map((section, i) => (
              <SectionCard
                key={section.id}
                course={course}
                section={section}
                index={i}
                lectureOffset={course.curriculum.slice(0, i).reduce((n, prev) => n + prev.lectures.length, 0)}
                locked={locked}
                onMoveLecture={moveLecture}
                onReorder={reorderWithin}
              />
            ))}
          </ol>
        </SortableContext>
      </DndContext>

      {!locked && <AddSection onAdd={(title) => addSection.mutateAsync(title)} />}
    </div>
  );
}

function SectionCard({
  course,
  section,
  index,
  lectureOffset,
  locked,
  onMoveLecture,
  onReorder,
}: {
  course: CourseEditor;
  section: EditorSection;
  index: number;
  /** Lectures in earlier sections; lecture numbers run on across the whole course. */
  lectureOffset: number;
  locked: boolean;
  onMoveLecture: (lectureId: number, sectionId: number) => void;
  onReorder: (sectionId: number, from: number, to: number) => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: `s-${section.id}`, disabled: locked });
  const [editing, setEditing] = useState(false);
  // The lecture just added here opens so its content can be added straight away.
  const [justAdded, setJustAdded] = useState<number | null>(null);
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));

  const rename = useEditorAction(course.id, (title: string) =>
    unwrap(api.PATCH("/instructor/sections/{section}", { params: { path: { section: section.id } }, body: { title } })),
  );
  const remove = useEditorAction(course.id, () => unwrap(api.DELETE("/instructor/sections/{section}", { params: { path: { section: section.id } } })));
  const addLecture = useEditorAction(course.id, (vars: { title: string; kind: LectureKind }) =>
    unwrap(api.POST("/instructor/sections/{section}/lectures", { params: { path: { section: section.id } }, body: vars })),
  );

  const onLectureDragEnd = ({ active, over }: DragEndEvent) => {
    if (!over || active.id === over.id) return;
    const ids = section.lectures.map((l) => `l-${l.id}`);
    onReorder(section.id, ids.indexOf(String(active.id)), ids.indexOf(String(over.id)));
  };

  return (
    <li ref={setNodeRef} style={{ transform: CSS.Translate.toString(transform), transition }} className={cn("rounded-md border border-line bg-white", isDragging && "relative z-10 shadow-lg")}>
      <div className="flex items-center gap-2 border-b border-line px-3 py-3">
        {!locked && (
          <button type="button" aria-label={`Reorder section ${index + 1}`} className="cursor-grab rounded-sm p-1 text-stone-500 hover:text-navy-900 active:cursor-grabbing" {...attributes} {...listeners}>
            <GripVertical className="size-4" />
          </button>
        )}
        {editing ? (
          <InlineTitle
            initial={section.title}
            onCancel={() => setEditing(false)}
            onSave={async (title) => {
              await rename.mutateAsync(title);
              setEditing(false);
            }}
          />
        ) : (
          <p className="flex-1 text-sm">
            <span className="font-bold text-ink">Section {index + 1}:</span> <span className="text-stone-700">{section.title}</span>
          </p>
        )}
        {!locked && !editing && (
          <>
            <IconAction label="Rename section" icon={Pencil} onClick={() => setEditing(true)} />
            <IconAction
              label="Delete section"
              icon={Trash2}
              onClick={() => {
                if (window.confirm(`Delete "${section.title}" and its ${plural(section.lectures.length, "lecture")}? Uploaded videos will be removed.`)) remove.mutate(undefined);
              }}
            />
          </>
        )}
      </div>

      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        onDragEnd={onLectureDragEnd}
        accessibility={{ announcements: announcements((id) => `lecture "${section.lectures.find((l) => `l-${l.id}` === id)?.title ?? ""}"`) }}
      >
        <SortableContext items={section.lectures.map((l) => `l-${l.id}`)} strategy={verticalListSortingStrategy}>
          <ol className="space-y-2 p-3">
            {section.lectures.map((lecture, j) => (
              <LectureRow
                key={lecture.id}
                course={course}
                lecture={lecture}
                index={lectureOffset + j}
                sectionId={section.id}
                locked={locked}
                autoOpen={lecture.id === justAdded}
                onMove={onMoveLecture}
              />
            ))}
          </ol>
        </SortableContext>
      </DndContext>

      {!locked && (
        <AddLecture
          onAdd={async (vars) => {
            const res = await addLecture.mutateAsync(vars);
            const ids = res.data.curriculum.find((s) => s.id === section.id)?.lectures.map((l) => l.id) ?? [];
            setJustAdded(Math.max(...ids));
          }}
        />
      )}
    </li>
  );
}

function LectureRow({
  course,
  lecture,
  index,
  sectionId,
  locked,
  autoOpen,
  onMove,
}: {
  course: CourseEditor;
  lecture: EditorLecture;
  index: number;
  sectionId: number;
  locked: boolean;
  autoOpen: boolean;
  onMove: (lectureId: number, sectionId: number) => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: `l-${lecture.id}`, disabled: locked });
  // Collapsed unless just added, until the instructor toggles it.
  const [toggled, setOpen] = useState<boolean | null>(null);
  const open = toggled ?? autoOpen;
  const [editing, setEditing] = useState(false);

  const update = useEditorAction(course.id, (body: { title?: string; is_free_preview?: boolean; min_edition_id?: number | null }) =>
    unwrap(api.PATCH("/instructor/lectures/{lecture}", { params: { path: { lecture: lecture.id } }, body })),
  );
  const remove = useEditorAction(course.id, () => unwrap(api.DELETE("/instructor/lectures/{lecture}", { params: { path: { lecture: lecture.id } } })));
  const KindIcon = kinds[lecture.kind].icon;

  return (
    <li ref={setNodeRef} style={{ transform: CSS.Translate.toString(transform), transition }} className={cn("rounded-sm border border-line bg-paper/60", isDragging && "relative z-10 bg-white shadow-md")}>
      <div className="flex items-center gap-2 px-2 py-2">
        {!locked && (
          <button type="button" aria-label={`Reorder lecture ${index + 1}`} className="cursor-grab rounded-sm p-1 text-stone-500/80 hover:text-navy-900 active:cursor-grabbing" {...attributes} {...listeners}>
            <GripVertical className="size-4" />
          </button>
        )}
        <KindIcon className="size-4 shrink-0 text-stone-500" strokeWidth={1.75} />
        {editing ? (
          <InlineTitle
            initial={lecture.title}
            onCancel={() => setEditing(false)}
            onSave={async (title) => {
              await update.mutateAsync({ title });
              setEditing(false);
            }}
          />
        ) : (
          <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="flex min-w-0 flex-1 items-center gap-2 text-left text-sm">
            <span className="truncate text-ink">
              <span className="text-stone-500">{kinds[lecture.kind].noun} {index + 1}:</span> {lecture.title}
            </span>
          </button>
        )}
        {!editing && <LectureStatus lecture={lecture} />}
        {!editing && (
          <button type="button" onClick={() => setOpen(!open)} aria-label={open ? "Collapse lecture" : "Expand lecture"} className="rounded-sm p-1.5 text-stone-500 hover:bg-paper-2">
            <ChevronDown className={cn("size-4 transition-transform", open && "rotate-180")} />
          </button>
        )}
      </div>

      {open && !editing && (
        <div className="space-y-5 border-t border-line bg-white px-4 py-4">
          {lecture.kind === "video" ? (
            <VideoPanel courseId={course.id} lecture={lecture} locked={locked} />
          ) : lecture.kind === "quiz" ? (
            <QuizPanel courseId={course.id} lecture={lecture} locked={locked} />
          ) : lecture.kind === "assignment" ? (
            <AssignmentPanel courseId={course.id} lecture={lecture} locked={locked} />
          ) : (
            <ArticlePanel courseId={course.id} lecture={lecture} locked={locked} />
          )}
          <ResourcesPanel courseId={course.id} lecture={lecture} locked={locked} editions={course.editions} />

          <div className="flex flex-wrap items-center gap-x-5 gap-y-3 border-t border-line pt-4 text-sm">
            <label className="flex items-center gap-2 text-stone-700">
              <input
                type="checkbox"
                checked={lecture.is_free_preview}
                disabled={locked || update.isPending}
                onChange={(e) => update.mutate({ is_free_preview: e.target.checked })}
                className="size-4 accent-[var(--navy-900)]"
              />
              Free preview (anyone can watch this lecture)
            </label>
            {course.editions.length > 0 && (
              <label className="flex items-center gap-2 text-stone-700">
                Available from
                <select
                  value={lecture.min_edition_id ?? ""}
                  disabled={locked || update.isPending}
                  onChange={(e) => update.mutate({ min_edition_id: e.target.value ? Number(e.target.value) : null })}
                  className="h-8 rounded-md border border-input bg-white px-2 text-sm"
                >
                  <option value="">Every edition</option>
                  {course.editions.slice(1).map((ed) => (
                    <option key={ed.id} value={ed.id}>
                      {ed.name} and above
                    </option>
                  ))}
                </select>
              </label>
            )}
            {course.curriculum.length > 1 && !locked && (
              <label className="flex items-center gap-2 text-stone-700">
                Move to
                <select
                  value={sectionId}
                  onChange={(e) => onMove(lecture.id, Number(e.target.value))}
                  className="h-8 rounded-md border border-input bg-white px-2 text-sm"
                >
                  {course.curriculum.map((s, i) => (
                    <option key={s.id} value={s.id}>
                      Section {i + 1}: {s.title}
                    </option>
                  ))}
                </select>
              </label>
            )}
            {!locked && (
              <span className="ml-auto flex gap-1">
                <Button type="button" size="sm" variant="ghost" onClick={() => setEditing(true)}>
                  <Pencil /> Rename
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  className="text-destructive"
                  onClick={() => {
                    if (window.confirm(`Delete "${lecture.title}"?`)) remove.mutate(undefined);
                  }}
                >
                  <Trash2 /> Delete
                </Button>
              </span>
            )}
          </div>
        </div>
      )}
    </li>
  );
}

function LectureStatus({ lecture }: { lecture: EditorLecture }) {
  if (lecture.kind === "quiz" || lecture.kind === "assignment") {
    return <span className="text-xs text-stone-500">{kinds[lecture.kind].label}</span>;
  }
  if (lecture.kind === "article") {
    return lecture.article_html ? (
      <span className="tabular text-xs text-stone-500">{formatClock(lecture.duration_seconds)} read</span>
    ) : (
      <span className="text-xs font-semibold text-gold-600">Add content</span>
    );
  }
  const v = lecture.video;
  if (!v) return <span className="text-xs font-semibold text-gold-600">Add video</span>;
  if (v.status === "ready")
    return (
      <span className="inline-flex items-center gap-1 text-xs text-stone-500">
        <CheckCircle2 className="size-3.5 text-success" /> <span className="tabular">{formatClock(v.duration_seconds)}</span>
        {lecture.is_free_preview && <span className="ml-1 rounded-sm bg-navy-100 px-1.5 py-0.5 text-[0.65rem] font-bold text-navy-800">PREVIEW</span>}
      </span>
    );
  if (v.status === "failed")
    return (
      <span className="inline-flex items-center gap-1 text-xs font-semibold text-destructive">
        <CircleAlert className="size-3.5" /> Failed
      </span>
    );
  return (
    <span className="inline-flex items-center gap-1 text-xs text-stone-700">
      <Loader2 className="size-3.5 animate-spin text-gold-600" /> Processing
    </span>
  );
}

function InlineTitle({ initial, onSave, onCancel }: { initial: string; onSave: (title: string) => Promise<void>; onCancel: () => void }) {
  const [value, setValue] = useState(initial);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (value.trim().length < 3) {
      setError("At least 3 characters.");
      return;
    }
    setBusy(true);
    try {
      await onSave(value.trim());
    } catch (e) {
      setError(e instanceof ApiRequestError ? e.message : "Couldn’t save.");
      setBusy(false);
    }
  };

  return (
    <div className="flex flex-1 items-center gap-2">
      <TextInput
        value={value}
        autoFocus
        maxLength={160}
        aria-label="Title"
        aria-invalid={!!error}
        className="h-9"
        onChange={(e) => setValue(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === "Enter") {
            e.preventDefault();
            void submit();
          }
          if (e.key === "Escape") onCancel();
        }}
      />
      <Button type="button" size="sm" onClick={submit} disabled={busy}>
        Save
      </Button>
      <Button type="button" size="sm" variant="ghost" onClick={onCancel}>
        Cancel
      </Button>
      {error && <span className="text-xs text-destructive">{error}</span>}
    </div>
  );
}

type LectureKind = EditorLecture["kind"];

const kinds: Record<LectureKind, { label: string; noun: string; icon: typeof FileText; placeholder: string }> = {
  video: { label: "Video", noun: "Lecture", icon: MonitorPlay, placeholder: "Lecture title, e.g. Filing GSTR-3B" },
  article: { label: "Article", noun: "Article", icon: FileText, placeholder: "Article title, e.g. GSTR-3B checklist" },
  quiz: { label: "Quiz", noun: "Quiz", icon: ListChecks, placeholder: "Quiz title, e.g. Check your understanding" },
  assignment: { label: "Assignment", noun: "Assignment", icon: ClipboardList, placeholder: "Assignment title, e.g. Reconcile GSTR-2B" },
};

function AddLecture({ onAdd }: { onAdd: (vars: { title: string; kind: LectureKind }) => Promise<unknown> }) {
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState("");
  const [kind, setKind] = useState<LectureKind>("video");
  const [busy, setBusy] = useState(false);

  if (!open) {
    return (
      <div className="px-3 pb-3">
        <button
          type="button"
          onClick={() => setOpen(true)}
          className="flex w-full items-center gap-2 rounded-sm border border-dashed border-input px-3 py-2.5 text-sm font-semibold text-navy-900 hover:border-navy-900 hover:bg-navy-100/40"
        >
          <Plus className="size-4" /> Add lecture
        </button>
      </div>
    );
  }

  return (
    <form
      className="mx-3 mb-3 space-y-3 rounded-sm border border-line bg-paper p-3"
      onSubmit={async (e) => {
        e.preventDefault();
        if (title.trim().length < 3) return;
        setBusy(true);
        try {
          await onAdd({ title: title.trim(), kind });
          setTitle("");
          setOpen(false);
        } finally {
          setBusy(false);
        }
      }}
    >
      <div className="flex gap-2" role="radiogroup" aria-label="Lecture type">
        {(["video", "article", "quiz", "assignment"] as const).map((k) => {
          const Icon = kinds[k].icon;
          return (
            <button
              key={k}
              type="button"
              role="radio"
              aria-checked={kind === k}
              onClick={() => setKind(k)}
              className={cn("inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold", kind === k ? "border-navy-900 bg-navy-900 text-white" : "border-line bg-white text-stone-700")}
            >
              <Icon className="size-3.5" />
              {kinds[k].label}
            </button>
          );
        })}
      </div>
      <div className="flex gap-2">
        <TextInput value={title} autoFocus maxLength={160} placeholder={kinds[kind].placeholder} aria-label="Lecture title" onChange={(e) => setTitle(e.target.value)} className="h-10" />
        <Button type="submit" disabled={busy || title.trim().length < 3}>
          Add
        </Button>
        <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
          Cancel
        </Button>
      </div>
    </form>
  );
}

function AddSection({ onAdd }: { onAdd: (title: string) => Promise<unknown> }) {
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState("");

  return open ? (
    <form
      className="mt-5 flex gap-2 rounded-md border border-line bg-white p-3"
      onSubmit={async (e) => {
        e.preventDefault();
        if (title.trim().length < 3) return;
        await onAdd(title.trim());
        setTitle("");
        setOpen(false);
      }}
    >
      <TextInput value={title} autoFocus maxLength={120} placeholder="Section title, e.g. Input tax credit" aria-label="Section title" onChange={(e) => setTitle(e.target.value)} className="h-10" />
      <Button type="submit" disabled={title.trim().length < 3}>
        Add section
      </Button>
      <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
        Cancel
      </Button>
    </form>
  ) : (
    <Button variant="outline" className="mt-5" onClick={() => setOpen(true)}>
      <Plus /> Add section
    </Button>
  );
}

function IconAction({ label, icon: Icon, onClick }: { label: string; icon: typeof Pencil; onClick: () => void }) {
  return (
    <button type="button" aria-label={label} onClick={onClick} className="rounded-sm p-1.5 text-stone-500 hover:bg-paper-2 hover:text-navy-900">
      <Icon className="size-3.5" />
    </button>
  );
}
