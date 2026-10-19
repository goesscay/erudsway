"use client";

import { useState } from "react";
import { ArrowLeft, BadgeCheck, EyeOff, MessageSquare, Search } from "lucide-react";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { FormAlert, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { ApiRequestError } from "@/lib/api/client";
import { useQaActions, useQuestion, useQuestions, type Question } from "@/lib/engagement/api";
import { plural, timeAgo } from "@/lib/format";
import { cn } from "@/lib/utils";

type Scope = "all" | "lecture" | "mine";

/**
 * Course Q&A inside the player. Learners ask and answer; the instructor's answers are
 * marked; the instructor (or a moderator) can hide posts.
 */
export function QaPanel({
  slug,
  lecture,
  canModerate,
  questionId,
  onOpenQuestion,
}: {
  slug: string;
  lecture: { id: number; title: string; number: number } | null;
  canModerate: boolean;
  /** The open thread, kept in the URL (?question=) so notification links land on it. */
  questionId: number | null;
  onOpenQuestion: (id: number | null) => void;
}) {
  if (questionId !== null) {
    return <Thread id={questionId} canModerate={canModerate} onBack={() => onOpenQuestion(null)} />;
  }
  return <QuestionList slug={slug} lecture={lecture} canModerate={canModerate} onOpen={onOpenQuestion} />;
}

function QuestionList({
  slug,
  lecture,
  canModerate,
  onOpen,
}: {
  slug: string;
  lecture: { id: number; title: string; number: number } | null;
  canModerate: boolean;
  onOpen: (id: number) => void;
}) {
  const [scope, setScope] = useState<Scope>("all");
  const [search, setSearch] = useState("");
  const [q, setQ] = useState("");
  const [page, setPage] = useState(1);
  const [asking, setAsking] = useState(false);
  const list = useQuestions(slug, {
    lecture: scope === "lecture" ? lecture?.id : undefined,
    mine: scope === "mine",
    q,
    page,
  });

  if (asking)
    return (
      <AskForm
        slug={slug}
        lecture={lecture}
        onDone={(id) => {
          setAsking(false);
          if (id) onOpen(id);
        }}
      />
    );

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <form
          className="relative flex-1"
          role="search"
          onSubmit={(e) => {
            e.preventDefault();
            setQ(search.trim());
            setPage(1);
          }}
        >
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-500" />
          <TextInput
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search all course questions"
            aria-label="Search questions"
            className="pl-9"
          />
        </form>
        {!canModerate && <Button onClick={() => setAsking(true)}>Ask a question</Button>}
      </div>

      <div className="flex flex-wrap gap-2" role="group" aria-label="Show">
        {(
          [
            ["all", "All lectures"],
            ["lecture", "This lecture"],
            ["mine", "My questions"],
          ] as const
        ).map(([value, label]) => (
          <button
            key={value}
            type="button"
            aria-pressed={scope === value}
            disabled={value === "lecture" && !lecture}
            onClick={() => {
              setScope(value);
              setPage(1);
            }}
            className={cn(
              "h-8 rounded-full border px-3.5 text-xs font-semibold disabled:opacity-40",
              scope === value
                ? "border-navy-900 bg-navy-900 text-white"
                : "border-line bg-white text-stone-700 hover:border-input",
            )}
          >
            {label}
          </button>
        ))}
      </div>

      {list.isPending ? (
        <Skeleton className="h-40 w-full" />
      ) : list.isError ? (
        <FormAlert>Couldn&rsquo;t load questions.</FormAlert>
      ) : list.data.data.length === 0 ? (
        <div className="rounded-md border border-dashed border-line bg-white px-6 py-10 text-center">
          <MessageSquare className="mx-auto size-8 text-stone-300" strokeWidth={1.5} />
          <p className="mt-3 text-sm text-stone-700">
            {q
              ? `No questions match “${q}”.`
              : scope === "mine"
                ? "You haven’t asked anything yet."
                : "No questions yet — be the first to ask."}
          </p>
        </div>
      ) : (
        <>
          <p className="text-sm font-semibold text-ink">{plural(list.data.meta.total, "question")} in this course</p>
          <ul
            className={cn(
              "divide-y divide-line rounded-md border border-line bg-white",
              list.isPlaceholderData && "opacity-60",
            )}
          >
            {list.data.data.map((question) => (
              <QuestionRow key={question.id} question={question} onOpen={() => onOpen(question.id)} />
            ))}
          </ul>
          {list.data.meta.last_page > 1 && (
            <div className="flex items-center justify-between text-sm">
              <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>
                Previous
              </Button>
              <span className="tabular text-stone-500">
                Page {list.data.meta.current_page} of {list.data.meta.last_page}
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={page >= list.data.meta.last_page}
                onClick={() => setPage(page + 1)}
              >
                Next
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}

export function QuestionRow({
  question,
  onOpen,
  showCourse,
}: {
  question: Question;
  onOpen: () => void;
  showCourse?: boolean;
}) {
  return (
    <li>
      <button type="button" onClick={onOpen} className="flex w-full gap-4 px-4 py-4 text-left hover:bg-paper">
        <InstructorAvatar name={question.author.name} className="size-9 shrink-0 text-xs" />
        <span className="min-w-0 flex-1">
          <span className="flex items-start gap-2">
            <span className="flex-1 font-semibold text-ink">{question.title}</span>
            {question.status === "hidden" && (
              <span className="inline-flex shrink-0 items-center gap-1 rounded-sm bg-paper-2 px-1.5 py-0.5 text-[0.65rem] font-bold text-stone-500 uppercase">
                <EyeOff className="size-3" /> Hidden
              </span>
            )}
          </span>
          <span className="mt-0.5 line-clamp-1 block text-sm text-stone-700">{question.body}</span>
          <span className="mt-1.5 flex flex-wrap items-center gap-x-2 text-xs text-stone-500">
            <span>{question.author.name}</span>
            {showCourse && <span>· {question.course.title}</span>}
            {question.lecture && <span>· {question.lecture.title}</span>}
            <span>· {timeAgo(question.last_activity_at)}</span>
          </span>
        </span>
        <span className="flex shrink-0 flex-col items-end gap-1 text-xs">
          <span className="tabular inline-flex items-center gap-1 font-semibold text-navy-800">
            {question.answers_count} <MessageSquare className="size-3.5" />
          </span>
          {question.instructor_answered && (
            <span className="inline-flex items-center gap-1 font-semibold text-success">
              <BadgeCheck className="size-3.5" /> Instructor
            </span>
          )}
        </span>
      </button>
    </li>
  );
}

function AskForm({
  slug,
  lecture,
  onDone,
}: {
  slug: string;
  lecture: { id: number; title: string; number: number } | null;
  onDone: (id: number | null) => void;
}) {
  const { ask } = useQaActions(slug);
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [onLecture, setOnLecture] = useState(!!lecture);
  const [error, setError] = useState<string | null>(null);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    ask.mutate(
      { title: title.trim(), body: body.trim(), lecture_id: onLecture && lecture ? lecture.id : null },
      {
        onSuccess: (res) => onDone(res.data.id),
        onError: (err) =>
          setError(
            err instanceof ApiRequestError
              ? (Object.values(err.fields)[0]?.[0] ?? err.message)
              : "Couldn’t post your question.",
          ),
      },
    );
  };

  return (
    <form onSubmit={submit} className="space-y-4 rounded-md border border-line bg-white p-5">
      <div>
        <h3 className="font-sans text-base font-bold text-ink">Ask a question</h3>
        <p className="mt-0.5 text-sm text-stone-500">
          Everyone in the course can see and answer it. The instructor is told straight away.
        </p>
      </div>
      {error && <FormAlert>{error}</FormAlert>}
      <div className="space-y-1.5">
        <label htmlFor="q-title" className="text-sm font-semibold text-ink">
          Title or summary
        </label>
        <TextInput
          id="q-title"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          maxLength={160}
          placeholder="e.g. Where does the figure in Table 8A come from?"
          autoFocus
        />
      </div>
      <div className="space-y-1.5">
        <label htmlFor="q-body" className="text-sm font-semibold text-ink">
          Details
        </label>
        <Textarea
          id="q-body"
          rows={5}
          value={body}
          onChange={(e) => setBody(e.target.value)}
          maxLength={5000}
          className="bg-white"
          placeholder="Add the context: the lecture, what you tried, the numbers involved."
        />
      </div>
      {lecture && (
        <label className="flex items-center gap-2 text-sm text-stone-700">
          <input
            type="checkbox"
            checked={onLecture}
            onChange={(e) => setOnLecture(e.target.checked)}
            className="size-4 accent-navy-900"
          />
          About lecture {lecture.number}: {lecture.title}
        </label>
      )}
      <div className="flex gap-2">
        <Button type="submit" disabled={title.trim().length < 8 || body.trim().length < 10 || ask.isPending}>
          {ask.isPending ? "Posting…" : "Post question"}
        </Button>
        <Button type="button" variant="outline" onClick={() => onDone(null)}>
          Cancel
        </Button>
      </div>
    </form>
  );
}

export function Thread({ id, canModerate, onBack }: { id: number; canModerate: boolean; onBack: () => void }) {
  const question = useQuestion(id);
  const { answer, hideQuestion, hideAnswer } = useQaActions(question.data?.course.slug);
  const [reply, setReply] = useState("");
  const [error, setError] = useState<string | null>(null);

  const back = (
    <button
      type="button"
      onClick={onBack}
      className="inline-flex items-center gap-1.5 text-sm font-semibold text-navy-800 hover:underline"
    >
      <ArrowLeft className="size-4" /> Back to all questions
    </button>
  );

  if (question.isPending) return <Skeleton className="h-48 w-full" />;
  if (question.isError) {
    return (
      <div className="space-y-4">
        {back}
        <FormAlert>This question isn&rsquo;t available. It may have been removed.</FormAlert>
      </div>
    );
  }

  const q = question.data;
  const send = () => {
    setError(null);
    answer.mutate(
      { id: q.id, body: reply.trim() },
      {
        onSuccess: () => setReply(""),
        onError: (e) =>
          setError(e instanceof ApiRequestError ? (e.fields.body?.[0] ?? e.message) : "Couldn’t post your answer."),
      },
    );
  };

  return (
    <div className="space-y-5">
      {back}
      <article className="rounded-md border border-line bg-white p-5">
        <div className="flex gap-4">
          <InstructorAvatar name={q.author.name} className="size-10 shrink-0 text-xs" />
          <div className="min-w-0 flex-1">
            <h3 className="font-sans text-lg font-bold text-ink">{q.title}</h3>
            <p className="mt-0.5 text-xs text-stone-500">
              {q.author.name}
              {q.lecture && <> · {q.lecture.title}</>} · {timeAgo(q.created_at)}
            </p>
            <p className="mt-3 text-sm leading-relaxed whitespace-pre-line text-stone-700">{q.body}</p>
            {canModerate && (
              <Button
                variant="ghost"
                size="sm"
                className="mt-2 -ml-2 text-stone-500"
                onClick={() => hideQuestion.mutate({ id: q.id, hidden: q.status !== "hidden" })}
              >
                <EyeOff /> {q.status === "hidden" ? "Show question again" : "Hide question"}
              </Button>
            )}
          </div>
        </div>
      </article>

      <h4 className="text-sm font-bold text-ink">
        {(q.answers?.length ?? 0) === 1 ? "1 reply" : `${q.answers?.length ?? 0} replies`}
      </h4>
      <ul className="space-y-4">
        {(q.answers ?? []).map((a) => (
          <li key={a.id} className={cn("flex gap-4", a.status === "hidden" && "opacity-50")}>
            <InstructorAvatar name={a.author.name} className="size-9 shrink-0 text-xs" />
            <div className="min-w-0 flex-1">
              <p className="flex flex-wrap items-center gap-2 text-sm">
                <span className="font-bold text-ink">{a.author.name}</span>
                {a.is_instructor && (
                  <span className="inline-flex items-center gap-1 rounded-sm bg-navy-900 px-1.5 py-0.5 text-[0.65rem] font-bold tracking-wide text-white uppercase">
                    <BadgeCheck className="size-3" /> Instructor
                  </span>
                )}
                <span className="text-xs text-stone-500">{timeAgo(a.created_at)}</span>
              </p>
              <p className="mt-1 text-sm leading-relaxed whitespace-pre-line text-stone-700">{a.body}</p>
              {canModerate && (
                <button
                  type="button"
                  className="mt-1 text-xs text-stone-500 hover:text-navy-900"
                  onClick={() => hideAnswer.mutate({ id: a.id, questionId: q.id, hidden: a.status !== "hidden" })}
                >
                  {a.status === "hidden" ? "Show reply" : "Hide reply"}
                </button>
              )}
            </div>
          </li>
        ))}
      </ul>

      {q.status !== "hidden" && (
        <div className="space-y-3 rounded-md border border-line bg-white p-4">
          <label htmlFor="answer-body" className="text-sm font-semibold text-ink">
            Write your reply
          </label>
          {error && <FormAlert>{error}</FormAlert>}
          <Textarea
            id="answer-body"
            rows={4}
            maxLength={5000}
            value={reply}
            onChange={(e) => setReply(e.target.value)}
            className="bg-white"
          />
          <Button onClick={send} disabled={reply.trim().length < 2 || answer.isPending}>
            {answer.isPending ? "Posting…" : "Add reply"}
          </Button>
        </div>
      )}
    </div>
  );
}
