"use client";

import { useState } from "react";
import { Check, CircleCheck, CircleX, Eye, RotateCcw, X } from "lucide-react";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiRequestError } from "@/lib/api/client";
import { useQuiz, useSubmitQuiz, type LearnerQuiz, type QuizResult } from "@/lib/assessment/api";
import { plural } from "@/lib/format";
import { cn } from "@/lib/utils";

type Stage = "intro" | "taking" | "result";

/** A quiz lecture, shown in place of the video: intro, the questions, then the marked result. */
export function QuizView({
  slug,
  lectureId,
  title,
  tracked,
  onNext,
}: {
  slug: string;
  lectureId: number;
  title: string;
  tracked: boolean;
  onNext?: () => void;
}) {
  const quiz = useQuiz(lectureId);

  return (
    <section className="max-h-[75vh] min-h-[50vh] overflow-y-auto border-b border-line bg-white px-6 py-10 sm:px-12">
      <div className="mx-auto max-w-[70ch]">
        {quiz.isPending ? (
          <div className="space-y-3">
            <Skeleton className="h-8 w-2/3" />
            <Skeleton className="h-4 w-1/2" />
          </div>
        ) : quiz.isError ? (
          <p className="text-sm text-stone-500">We couldn’t load this quiz. Refresh to try again.</p>
        ) : (
          <Quiz
            key={lectureId}
            slug={slug}
            lectureId={lectureId}
            title={title}
            quiz={quiz.data}
            tracked={tracked}
            onNext={onNext}
          />
        )}
      </div>
    </section>
  );
}

function Quiz({
  slug,
  lectureId,
  title,
  quiz,
  tracked,
  onNext,
}: {
  slug: string;
  lectureId: number;
  title: string;
  quiz: LearnerQuiz;
  tracked: boolean;
  onNext?: () => void;
}) {
  const submit = useSubmitQuiz(lectureId, slug);
  const [stage, setStage] = useState<Stage>("intro");
  const [answers, setAnswers] = useState<Record<number, number[]>>({});
  const [result, setResult] = useState<QuizResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const shown = result ?? quiz.last_result;
  const answered = quiz.questions.filter((q) => (answers[q.id]?.length ?? 0) > 0).length;

  const start = () => {
    setAnswers({});
    setResult(null);
    setError(null);
    setStage("taking");
  };

  const send = () => {
    setError(null);
    submit.mutate(answers, {
      onSuccess: (res) => {
        setResult(res.data);
        setStage("result");
      },
      onError: (e) => setError(e instanceof ApiRequestError ? e.message : "Couldn’t submit your answers."),
    });
  };

  if (quiz.questions.length === 0) {
    return (
      <>
        <h2 className="text-h2">{title}</h2>
        <p className="mt-3 text-sm text-stone-500">This quiz has no questions yet.</p>
      </>
    );
  }

  if (stage === "taking") {
    return (
      <>
        <p className="text-xs font-bold tracking-[0.2em] text-gold-600 uppercase">Quiz</p>
        <h2 className="mt-1 text-h2">{title}</h2>
        {!tracked && <PreviewNote />}
        <ol className="mt-8 space-y-8">
          {quiz.questions.map((q, i) => (
            <li key={q.id}>
              <fieldset>
                <legend className="text-[0.95rem] font-semibold text-ink">
                  <span className="text-stone-500">{i + 1}.</span>{" "}
                  <span className="whitespace-pre-line">{q.prompt}</span>
                </legend>
                {q.type === "multiple" && <p className="mt-1 text-xs text-stone-500">Choose all that apply.</p>}
                <div className="mt-3 space-y-2">
                  {q.options.map((o) => {
                    const picked = answers[q.id]?.includes(o.id) ?? false;
                    return (
                      <label
                        key={o.id}
                        className={cn(
                          "flex cursor-pointer items-start gap-3 rounded-md border px-4 py-3 text-sm transition-colors",
                          picked ? "border-navy-900 bg-navy-100/40" : "border-line hover:border-input",
                        )}
                      >
                        <input
                          type={q.type === "single" ? "radio" : "checkbox"}
                          name={`q-${q.id}`}
                          checked={picked}
                          onChange={(e) =>
                            setAnswers((a) => {
                              const current = a[q.id] ?? [];
                              const next =
                                q.type === "single"
                                  ? [o.id]
                                  : e.target.checked
                                    ? [...current, o.id]
                                    : current.filter((id) => id !== o.id);
                              return { ...a, [q.id]: next };
                            })
                          }
                          className="mt-0.5 size-4 shrink-0 accent-[var(--navy-900)]"
                        />
                        <span className="text-stone-700">{o.body}</span>
                      </label>
                    );
                  })}
                </div>
              </fieldset>
            </li>
          ))}
        </ol>
        {error && (
          <div className="mt-6">
            <FormAlert>{error}</FormAlert>
          </div>
        )}
        <div className="mt-8 flex flex-wrap items-center gap-3 border-t border-line pt-6">
          <Button onClick={send} disabled={answered < quiz.questions.length || submit.isPending}>
            {submit.isPending ? "Checking…" : "Submit answers"}
          </Button>
          <Button variant="ghost" onClick={() => setStage("intro")}>
            Cancel
          </Button>
          <span className="tabular ml-auto text-sm text-stone-500">
            {answered} of {quiz.questions.length} answered
          </span>
        </div>
      </>
    );
  }

  if (stage === "result" && shown) {
    return <Result quiz={quiz} result={shown} title={title} tracked={tracked} onRetake={start} onNext={onNext} />;
  }

  // Intro
  const { attempts } = quiz;
  return (
    <>
      <p className="text-xs font-bold tracking-[0.2em] text-gold-600 uppercase">Quiz</p>
      <h2 className="mt-1 text-h2">{title}</h2>
      {!tracked && <PreviewNote />}
      <ul className="mt-5 flex flex-wrap gap-x-6 gap-y-1 text-sm text-stone-700">
        <li>{plural(quiz.questions.length, "question")}</li>
        <li>{quiz.pass_percent > 0 ? `Pass mark ${quiz.pass_percent}%` : "Any attempt completes it"}</li>
        <li>No time limit · retake as often as you like</li>
      </ul>
      {attempts.count > 0 && (
        <div
          className={cn(
            "mt-6 flex items-center gap-3 rounded-md px-4 py-3 text-sm",
            attempts.passed ? "bg-[#eef6f1] text-success" : "bg-paper-2 text-stone-700",
          )}
        >
          {attempts.passed ? <CircleCheck className="size-5 shrink-0" /> : <RotateCcw className="size-5 shrink-0" />}
          <span>
            {attempts.passed ? "Passed" : "Not passed yet"} · best score {attempts.best_percent}% ·{" "}
            {plural(attempts.count, "attempt")}
          </span>
        </div>
      )}
      <div className="mt-8 flex flex-wrap gap-3">
        <Button onClick={start}>{attempts.count > 0 ? "Retake quiz" : "Start quiz"}</Button>
        {quiz.last_result && (
          <Button variant="outline" onClick={() => setStage("result")}>
            Review last attempt
          </Button>
        )}
      </div>
    </>
  );
}

function Result({
  quiz,
  result,
  title,
  tracked,
  onRetake,
  onNext,
}: {
  quiz: LearnerQuiz;
  result: QuizResult;
  title: string;
  tracked: boolean;
  onRetake: () => void;
  onNext?: () => void;
}) {
  const byId = new Map(quiz.questions.map((q) => [q.id, q]));

  return (
    <>
      <p className="text-xs font-bold tracking-[0.2em] text-gold-600 uppercase">{title}</p>
      <div className="mt-3 flex items-center gap-5">
        <p
          className={cn("tabular font-serif text-5xl font-semibold", result.passed ? "text-success" : "text-navy-900")}
        >
          {result.score_percent}%
        </p>
        <div>
          <p className="text-lg font-bold text-ink">
            {result.passed ? (result.pass_percent > 0 ? "You passed" : "Done") : "Not quite"}
          </p>
          <p className="text-sm text-stone-500">
            {result.correct_count} of {result.question_count} correct
            {result.pass_percent > 0 && ` · pass mark ${result.pass_percent}%`}
          </p>
        </div>
      </div>
      {!tracked && <PreviewNote />}

      <ol className="mt-8 space-y-6">
        {result.review.map((r, i) => {
          const q = byId.get(r.question_id);
          if (!q) return null;
          return (
            <li key={r.question_id} className="rounded-md border border-line p-4">
              <p className="flex gap-2 text-[0.95rem] font-semibold text-ink">
                {r.is_correct ? (
                  <CircleCheck className="mt-0.5 size-4 shrink-0 text-success" />
                ) : (
                  <CircleX className="mt-0.5 size-4 shrink-0 text-destructive" />
                )}
                <span className="whitespace-pre-line">
                  <span className="sr-only">{r.is_correct ? "Correct: " : "Incorrect: "}</span>
                  {i + 1}. {q.prompt}
                </span>
              </p>
              <ul className="mt-3 space-y-1.5 pl-6 text-sm">
                {q.options.map((o) => {
                  const chosen = r.chosen.includes(o.id);
                  const correct = r.correct.includes(o.id);
                  return (
                    <li
                      key={o.id}
                      className={cn(
                        "flex items-start gap-2 rounded-sm px-2 py-1",
                        correct && "bg-[#eef6f1] text-success",
                        chosen && !correct && "bg-destructive/5 text-destructive",
                        !chosen && !correct && "text-stone-500",
                      )}
                    >
                      {correct ? (
                        <Check className="mt-0.5 size-3.5 shrink-0" />
                      ) : chosen ? (
                        <X className="mt-0.5 size-3.5 shrink-0" />
                      ) : (
                        <span className="size-3.5 shrink-0" />
                      )}
                      <span>
                        {o.body}
                        {chosen && <span className="ml-1.5 text-xs font-semibold">(your answer)</span>}
                      </span>
                    </li>
                  );
                })}
              </ul>
              {r.explanation && (
                <p className="mt-3 rounded-sm bg-paper-2/70 px-3 py-2 text-sm whitespace-pre-line text-stone-700">
                  {r.explanation}
                </p>
              )}
            </li>
          );
        })}
      </ol>

      <div className="mt-8 flex flex-wrap gap-3 border-t border-line pt-6">
        {result.passed && onNext && <Button onClick={onNext}>Continue to next lecture</Button>}
        <Button variant={result.passed ? "outline" : "default"} onClick={onRetake}>
          <RotateCcw /> Retake quiz
        </Button>
      </div>
    </>
  );
}

function PreviewNote() {
  return (
    <p className="mt-3 inline-flex items-center gap-1.5 rounded-sm bg-paper-2 px-2.5 py-1 text-xs font-semibold text-stone-700">
      <Eye className="size-3.5" /> Preview — your answers are checked but not saved
    </p>
  );
}
