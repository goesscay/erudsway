"use client";

import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowDown, ArrowUp, Plus, Trash2 } from "lucide-react";
import { RichTextEditor } from "@/components/authoring/rich-text-editor";
import { FormAlert, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { editorKey, type EditorLecture } from "@/lib/authoring/editor";
import { cn } from "@/lib/utils";

type QuizType = "single" | "multiple";
type DraftOption = { key: string; id?: number; body: string; is_correct: boolean };
type DraftQuestion = {
  key: string;
  id?: number;
  type: QuizType;
  prompt: string;
  explanation: string;
  options: DraftOption[];
};

let seq = 0;
const key = () => `k${++seq}`;
const blankOption = (): DraftOption => ({ key: key(), body: "", is_correct: false });
const blankQuestion = (): DraftQuestion => ({
  key: key(),
  type: "single",
  prompt: "",
  explanation: "",
  options: [blankOption(), blankOption(), blankOption()],
});

/** What's wrong with a question, or null when it's ready to save. */
function problem(q: DraftQuestion): string | null {
  if (!q.prompt.trim()) return "Write the question.";
  const filled = q.options.filter((o) => o.body.trim());
  if (filled.length < 2) return "Add at least two answers.";
  const correct = filled.filter((o) => o.is_correct).length;
  if (q.type === "single" && correct !== 1) return "Mark the one correct answer.";
  if (correct === 0) return "Mark at least one correct answer.";
  return null;
}

/** Question editor for a quiz lecture. Saves the whole quiz at once. */
export function QuizPanel({
  courseId,
  lecture,
  locked,
}: {
  courseId: string;
  lecture: EditorLecture;
  locked: boolean;
}) {
  const quiz = useQuery({
    queryKey: ["quiz-editor", lecture.id],
    queryFn: () => loadQuiz(lecture.id),
  });

  if (quiz.isPending) return <Skeleton className="h-40 w-full" />;
  if (quiz.isError) return <FormAlert>Couldn&rsquo;t load the quiz.</FormAlert>;
  return <QuizEditor courseId={courseId} lectureId={lecture.id} locked={locked} initial={quiz.data} />;
}

function QuizEditor({
  courseId,
  lectureId,
  locked,
  initial,
}: {
  courseId: string;
  lectureId: number;
  locked: boolean;
  initial: QuizData;
}) {
  const queryClient = useQueryClient();
  const fromServer = (data: QuizData) =>
    data.questions.map((q) => ({
      key: key(),
      id: q.id,
      type: q.type,
      prompt: q.prompt,
      explanation: q.explanation ?? "",
      options: q.options.map((o) => ({ key: key(), id: o.id, body: o.body, is_correct: o.is_correct })),
    }));
  const [questions, setQuestions] = useState<DraftQuestion[]>(() =>
    initial.questions.length ? fromServer(initial) : [blankQuestion()],
  );
  const [pass, setPass] = useState(initial.pass_percent);
  const [shuffle, setShuffle] = useState(initial.shuffle_questions);
  const [dirty, setDirty] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const change = (fn: (qs: DraftQuestion[]) => DraftQuestion[]) => {
    setQuestions(fn);
    setDirty(true);
    setSaved(false);
  };
  const patch = (k: string, p: Partial<DraftQuestion>) =>
    change((qs) => qs.map((q) => (q.key === k ? { ...q, ...p } : q)));
  const move = (i: number, by: number) =>
    change((qs) => {
      const next = [...qs];
      const [q] = next.splice(i, 1);
      next.splice(i + by, 0, q!);
      return next;
    });

  const firstProblem = questions.map(problem).findIndex((p) => p !== null);

  const save = async () => {
    setBusy(true);
    setError(null);
    try {
      const res = await mutate(() =>
        api.PUT("/instructor/lectures/{lecture}/quiz", {
          params: { path: { lecture: lectureId } },
          body: {
            pass_percent: pass,
            shuffle_questions: shuffle,
            questions: questions.map((q) => ({
              id: q.id ?? null,
              type: q.type,
              prompt: q.prompt.trim(),
              explanation: q.explanation.trim() || null,
              options: q.options
                .filter((o) => o.body.trim())
                .map((o) => ({ id: o.id ?? null, body: o.body.trim(), is_correct: o.is_correct })),
            })),
          },
        }),
      );
      setQuestions(fromServer(res.data));
      queryClient.setQueryData(["quiz-editor", lectureId], res.data);
      await queryClient.invalidateQueries({ queryKey: editorKey(courseId) });
      setDirty(false);
      setSaved(true);
    } catch (e) {
      setError(e instanceof ApiRequestError ? e.message : "Couldn’t save the quiz.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-4">
      {error && <FormAlert>{error}</FormAlert>}
      <ol className="space-y-4">
        {questions.map((q, i) => {
          const issue = dirty ? problem(q) : null;
          return (
            <li key={q.key} className="rounded-md border border-line bg-paper/50 p-4">
              <div className="mb-3 flex items-center gap-2">
                <span className="text-sm font-bold text-ink">Question {i + 1}</span>
                <select
                  aria-label={`Question ${i + 1} type`}
                  value={q.type}
                  disabled={locked}
                  onChange={(e) => {
                    const type = e.target.value as QuizType;
                    // Going to single choice keeps only the first correct answer.
                    let seen = false;
                    const options =
                      type === "single"
                        ? q.options.map((o) => {
                            const keep = o.is_correct && !seen;
                            if (o.is_correct) seen = true;
                            return { ...o, is_correct: keep };
                          })
                        : q.options;
                    patch(q.key, { type, options });
                  }}
                  className="h-8 rounded-md border border-input bg-white px-2 text-xs font-semibold"
                >
                  <option value="single">One correct answer</option>
                  <option value="multiple">Several correct answers</option>
                </select>
                <span className="ml-auto flex gap-1">
                  <IconButton label="Move question up" disabled={locked || i === 0} onClick={() => move(i, -1)}>
                    <ArrowUp className="size-3.5" />
                  </IconButton>
                  <IconButton
                    label="Move question down"
                    disabled={locked || i === questions.length - 1}
                    onClick={() => move(i, 1)}
                  >
                    <ArrowDown className="size-3.5" />
                  </IconButton>
                  <IconButton
                    label="Delete question"
                    disabled={locked || questions.length === 1}
                    onClick={() => change((qs) => qs.filter((x) => x.key !== q.key))}
                  >
                    <Trash2 className="size-3.5" />
                  </IconButton>
                </span>
              </div>
              <Textarea
                aria-label={`Question ${i + 1}`}
                rows={2}
                maxLength={2000}
                value={q.prompt}
                disabled={locked}
                onChange={(e) => patch(q.key, { prompt: e.target.value })}
                placeholder="e.g. Which return reports outward supplies?"
                className="bg-white"
              />
              <fieldset className="mt-3 space-y-2">
                <legend className="mb-1 text-xs font-semibold text-stone-500">
                  Answers — tick the correct {q.type === "single" ? "one" : "ones"}
                </legend>
                {q.options.map((o, j) => (
                  <div key={o.key} className="flex items-center gap-2">
                    <input
                      type={q.type === "single" ? "radio" : "checkbox"}
                      name={`correct-${q.key}`}
                      aria-label={`Answer ${j + 1} is correct`}
                      checked={o.is_correct}
                      disabled={locked}
                      onChange={(e) =>
                        patch(q.key, {
                          options: q.options.map((x) =>
                            x.key === o.key
                              ? { ...x, is_correct: e.target.checked }
                              : q.type === "single"
                                ? { ...x, is_correct: false }
                                : x,
                          ),
                        })
                      }
                      className="size-4 shrink-0 accent-[var(--navy-900)]"
                    />
                    <TextInput
                      aria-label={`Answer ${j + 1}`}
                      value={o.body}
                      maxLength={500}
                      disabled={locked}
                      onChange={(e) =>
                        patch(q.key, {
                          options: q.options.map((x) => (x.key === o.key ? { ...x, body: e.target.value } : x)),
                        })
                      }
                      className={cn("h-9 text-sm", o.is_correct && "border-success/60")}
                    />
                    <IconButton
                      label={`Remove answer ${j + 1}`}
                      disabled={locked || q.options.length <= 2}
                      onClick={() => patch(q.key, { options: q.options.filter((x) => x.key !== o.key) })}
                    >
                      <Trash2 className="size-3.5" />
                    </IconButton>
                  </div>
                ))}
                {q.options.length < 10 && !locked && (
                  <button
                    type="button"
                    onClick={() => patch(q.key, { options: [...q.options, blankOption()] })}
                    className="inline-flex items-center gap-1 text-xs font-semibold text-navy-800 hover:underline"
                  >
                    <Plus className="size-3.5" /> Add answer
                  </button>
                )}
              </fieldset>
              <Textarea
                aria-label={`Explanation for question ${i + 1}`}
                rows={2}
                maxLength={2000}
                value={q.explanation}
                disabled={locked}
                onChange={(e) => patch(q.key, { explanation: e.target.value })}
                placeholder="Explanation shown after an attempt (optional)"
                className="mt-3 bg-white text-sm"
              />
              {issue && <p className="mt-2 text-xs font-medium text-destructive">{issue}</p>}
            </li>
          );
        })}
      </ol>

      {!locked && questions.length < 100 && (
        <Button type="button" variant="outline" size="sm" onClick={() => change((qs) => [...qs, blankQuestion()])}>
          <Plus /> Add question
        </Button>
      )}

      <div className="flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-line pt-4 text-sm">
        <label className="flex items-center gap-2 text-stone-700">
          Pass mark
          <TextInput
            type="number"
            min={0}
            max={100}
            value={pass}
            disabled={locked}
            onChange={(e) => {
              setPass(Math.max(0, Math.min(100, Number(e.target.value) || 0)));
              setDirty(true);
              setSaved(false);
            }}
            className="h-9 w-20 text-sm"
            aria-describedby="pass-hint"
          />
          %
        </label>
        <label className="flex items-center gap-2 text-stone-700">
          <input
            type="checkbox"
            checked={shuffle}
            disabled={locked}
            onChange={(e) => {
              setShuffle(e.target.checked);
              setDirty(true);
              setSaved(false);
            }}
            className="size-4 accent-[var(--navy-900)]"
          />
          Shuffle question order
        </label>
        <p id="pass-hint" className="w-full text-xs text-stone-500">
          Learners retry as often as they like; the lecture completes once they reach the pass mark. 0% means any
          attempt completes it.
        </p>
      </div>

      <div className="flex items-center gap-3">
        <Button
          type="button"
          size="sm"
          disabled={locked || !dirty || busy || firstProblem !== -1}
          onClick={() => void save()}
        >
          {busy ? "Saving…" : "Save quiz"}
        </Button>
        {dirty && firstProblem !== -1 && (
          <span className="text-xs text-destructive">Fix question {firstProblem + 1} to save.</span>
        )}
        {dirty && firstProblem === -1 && <span className="text-xs text-stone-500">Unsaved changes</span>}
        {saved && <span className="text-xs font-semibold text-success">Saved</span>}
      </div>
    </div>
  );
}

async function loadQuiz(lecture: number) {
  return (await unwrap(api.GET("/instructor/lectures/{lecture}/quiz", { params: { path: { lecture } } }))).data;
}

type QuizData = Awaited<ReturnType<typeof loadQuiz>>;

function IconButton({
  label,
  disabled,
  onClick,
  children,
}: {
  label: string;
  disabled?: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      aria-label={label}
      disabled={disabled}
      onClick={onClick}
      className="rounded-sm p-1.5 text-stone-500 hover:bg-paper-2 hover:text-navy-900 disabled:pointer-events-none disabled:opacity-30"
    >
      {children}
    </button>
  );
}

/** Brief for an assignment lecture. */
export function AssignmentPanel({
  courseId,
  lecture,
  locked,
}: {
  courseId: string;
  lecture: EditorLecture;
  locked: boolean;
}) {
  const brief = useQuery({
    queryKey: ["assignment-editor", lecture.id],
    queryFn: async () =>
      (
        await unwrap(
          api.GET("/instructor/lectures/{lecture}/assignment", { params: { path: { lecture: lecture.id } } }),
        )
      ).data,
  });

  if (brief.isPending) return <Skeleton className="h-40 w-full" />;
  if (brief.isError) return <FormAlert>Couldn&rsquo;t load the assignment.</FormAlert>;
  return (
    <AssignmentEditor
      courseId={courseId}
      lectureId={lecture.id}
      locked={locked}
      html={brief.data.instructions_html ?? ""}
      allowFile={brief.data.allow_file}
    />
  );
}

function AssignmentEditor({
  courseId,
  lectureId,
  locked,
  html: initialHtml,
  allowFile: initialAllow,
}: {
  courseId: string;
  lectureId: number;
  locked: boolean;
  html: string;
  allowFile: boolean;
}) {
  const queryClient = useQueryClient();
  const [html, setHtml] = useState(initialHtml);
  const [allowFile, setAllowFile] = useState(initialAllow);
  const [baseline, setBaseline] = useState({ html: initialHtml, allowFile: initialAllow });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const dirty = html !== baseline.html || allowFile !== baseline.allowFile;

  const save = async () => {
    setBusy(true);
    setError(null);
    try {
      const res = await mutate(() =>
        api.PUT("/instructor/lectures/{lecture}/assignment", {
          params: { path: { lecture: lectureId } },
          body: { instructions_html: html || null, allow_file: allowFile },
        }),
      );
      const saved = { html: res.data.instructions_html ?? "", allowFile: res.data.allow_file };
      setBaseline(saved);
      setHtml(saved.html);
      await queryClient.invalidateQueries({ queryKey: editorKey(courseId) });
    } catch (e) {
      setError(e instanceof ApiRequestError ? e.message : "Couldn’t save the assignment.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-3">
      {error && <FormAlert>{error}</FormAlert>}
      <p className="text-sm font-semibold text-ink">Instructions</p>
      <RichTextEditor
        value={html}
        onChange={setHtml}
        minHeight={180}
        placeholder="What should learners do, and what should they hand in?"
      />
      <label className="flex items-center gap-2 text-sm text-stone-700">
        <input
          type="checkbox"
          checked={allowFile}
          disabled={locked}
          onChange={(e) => setAllowFile(e.target.checked)}
          className="size-4 accent-[var(--navy-900)]"
        />
        Learners can attach a file (PDF, Word, Excel, images or ZIP, up to 20 MB)
      </label>
      <p className="text-xs text-stone-500">
        The lecture completes when a learner hands in. Their answers arrive under Assignments in your dashboard.
      </p>
      <div className="flex items-center gap-3">
        <Button type="button" size="sm" disabled={locked || !dirty || busy} onClick={() => void save()}>
          {busy ? "Saving…" : "Save assignment"}
        </Button>
        {dirty && <span className="text-xs text-stone-500">Unsaved changes</span>}
      </div>
    </div>
  );
}
