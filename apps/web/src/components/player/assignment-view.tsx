"use client";

import { useRef, useState } from "react";
import { CircleCheck, FileText, MessageSquareReply, Paperclip, X } from "lucide-react";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { ApiRequestError } from "@/lib/api/client";
import { useAssignment, useSubmitAssignment, type LearnerAssignment } from "@/lib/assessment/api";
import { timeAgo } from "@/lib/format";

/** An assignment lecture: the brief, and the learner's hand-in and feedback. */
export function AssignmentView({
  slug,
  lectureId,
  title,
  tracked,
}: {
  slug: string;
  lectureId: number;
  title: string;
  tracked: boolean;
}) {
  const assignment = useAssignment(lectureId);

  return (
    <section className="max-h-[75vh] min-h-[50vh] overflow-y-auto border-b border-line bg-white px-6 py-10 sm:px-12">
      <div className="mx-auto max-w-[70ch]">
        <p className="text-xs font-bold tracking-[0.2em] text-gold-600 uppercase">Assignment</p>
        <h2 className="mt-1 text-h2">{title}</h2>
        {assignment.isPending ? (
          <Skeleton className="mt-6 h-32 w-full" />
        ) : assignment.isError ? (
          <p className="mt-4 text-sm text-stone-500">We couldn’t load this assignment. Refresh to try again.</p>
        ) : (
          <>
            {assignment.data.instructions_html ? (
              // Sanitised server-side (HTMLPurifier) when the instructor saved it.
              <div
                className="prose-content mt-6 leading-relaxed text-stone-700"
                dangerouslySetInnerHTML={{ __html: assignment.data.instructions_html }}
              />
            ) : (
              <p className="mt-4 text-sm text-stone-500">The instructor hasn’t written the instructions yet.</p>
            )}
            <div className="mt-10 border-t border-line pt-6">
              {tracked ? (
                <HandIn key={lectureId} slug={slug} lectureId={lectureId} assignment={assignment.data} />
              ) : (
                <p className="text-sm text-stone-500">
                  Learners hand in their answer here. Submissions reach you under Assignments in your dashboard.
                </p>
              )}
            </div>
          </>
        )}
      </div>
    </section>
  );
}

function HandIn({ slug, lectureId, assignment }: { slug: string; lectureId: number; assignment: LearnerAssignment }) {
  const submission = assignment.submission;
  const submit = useSubmitAssignment(lectureId, slug);
  const [editing, setEditing] = useState(submission === null);
  const [body, setBody] = useState(submission?.body ?? "");
  const [file, setFile] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);
  const input = useRef<HTMLInputElement>(null);
  const reviewed = submission?.status === "reviewed";

  if (submission && !editing) {
    return (
      <div className="space-y-4">
        <p className="flex items-center gap-2 text-sm font-semibold text-success">
          <CircleCheck className="size-4" /> Handed in {timeAgo(submission.submitted_at)}
        </p>
        {submission.body && (
          <p className="rounded-md bg-paper-2/70 px-4 py-3 text-sm whitespace-pre-line text-stone-700">
            {submission.body}
          </p>
        )}
        {submission.file && (
          <a
            href={submission.file.url}
            className="inline-flex items-center gap-2 text-sm font-semibold text-navy-800 hover:underline"
          >
            <FileText className="size-4" /> {submission.file.name}
          </a>
        )}
        {reviewed ? (
          <div className="rounded-md border-l-2 border-gold-500 bg-paper-2/60 px-4 py-3">
            <p className="flex items-center gap-1.5 text-xs font-bold text-navy-900">
              <MessageSquareReply className="size-3.5" /> Feedback from your instructor
            </p>
            <p className="mt-1 text-sm whitespace-pre-line text-stone-700">{submission.feedback}</p>
          </div>
        ) : (
          <div className="flex flex-wrap items-center gap-3">
            <p className="text-sm text-stone-500">Waiting for your instructor&rsquo;s feedback.</p>
            <Button variant="outline" size="sm" onClick={() => setEditing(true)}>
              Replace my answer
            </Button>
          </div>
        )}
      </div>
    );
  }

  const send = () => {
    setError(null);
    submit.mutate(
      { body, file },
      {
        onSuccess: () => {
          setFile(null);
          setEditing(false);
        },
        onError: (e) =>
          setError(
            e instanceof ApiRequestError
              ? (Object.values(e.fields)[0]?.[0] ?? e.message)
              : "Couldn’t hand in your answer.",
          ),
      },
    );
  };

  return (
    <div className="space-y-4">
      <h3 className="font-sans text-base font-bold text-ink">Your answer</h3>
      {error && <FormAlert>{error}</FormAlert>}
      <Textarea
        aria-label="Your answer"
        rows={6}
        maxLength={20000}
        value={body}
        onChange={(e) => setBody(e.target.value)}
        className="bg-white"
        placeholder="Write your answer, or summarise what you've attached."
      />
      {assignment.allow_file && (
        <div className="flex flex-wrap items-center gap-3 text-sm">
          <input
            ref={input}
            type="file"
            className="sr-only"
            accept={assignment.file_types.map((t) => `.${t}`).join(",")}
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            aria-label="Attach a file"
          />
          <Button type="button" variant="outline" size="sm" onClick={() => input.current?.click()}>
            <Paperclip /> {file ? "Change file" : submission?.file ? "Replace file" : "Attach a file"}
          </Button>
          {file ? (
            <span className="inline-flex items-center gap-1.5 text-stone-700">
              {file.name}
              <button
                type="button"
                aria-label="Remove file"
                onClick={() => {
                  setFile(null);
                  if (input.current) input.current.value = "";
                }}
                className="rounded-sm p-0.5 text-stone-500 hover:text-destructive"
              >
                <X className="size-3.5" />
              </button>
            </span>
          ) : submission?.file ? (
            <span className="text-stone-500">Current: {submission.file.name}</span>
          ) : (
            <span className="text-xs text-stone-500">
              {assignment.file_types.map((t) => t.toUpperCase()).join(", ")} · up to {assignment.max_file_mb} MB
            </span>
          )}
        </div>
      )}
      <div className="flex gap-2">
        <Button onClick={send} disabled={submit.isPending || (!body.trim() && !file && !submission?.file)}>
          {submit.isPending ? "Handing in…" : submission ? "Hand in again" : "Hand in"}
        </Button>
        {submission && (
          <Button variant="ghost" onClick={() => setEditing(false)}>
            Cancel
          </Button>
        )}
      </div>
    </div>
  );
}
