"use client";

import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Megaphone, Send } from "lucide-react";
import { RichTextEditor } from "@/components/authoring/rich-text-editor";
import { AnnouncementCard } from "@/components/engagement/announcements-panel";
import { Field, FormAlert, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiRequestError, unwrap } from "@/lib/api/client";
import type { InstructorCourse } from "@/lib/authoring/editor";
import { useMe } from "@/lib/auth/session";
import { postAnnouncement, useInstructorAnnouncements } from "@/lib/engagement/api";
import { plural } from "@/lib/format";

export function AnnouncementsView() {
  const { data: me } = useMe();
  const courses = useQuery({
    queryKey: ["instructor-courses"],
    enabled: !!me?.instructor,
    queryFn: async () => (await unwrap(api.GET("/instructor/courses"))).data as InstructorCourse[],
  });
  const published = (courses.data ?? []).filter((c) => c.status === "published");
  const [picked, setPicked] = useState<string | null>(null);
  const courseId = picked ?? published[0]?.id ?? null;
  const course = published.find((c) => c.id === courseId);

  return (
    <>
      <header className="border-b border-line bg-white px-4 py-6 sm:px-8">
        <h1 className="text-h2">Announcements</h1>
        <p className="mt-1 text-sm text-stone-500">
          Email and notify everyone enrolled in a course — new lectures, law changes, live sessions. Up to four per
          course a month.
        </p>
      </header>

      <div className="mx-auto max-w-4xl space-y-8 px-4 py-8 sm:px-8">
        {courses.isPending ? (
          <Skeleton className="h-64 w-full" />
        ) : published.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center">
            <Megaphone className="mx-auto size-8 text-stone-300" strokeWidth={1.5} />
            <p className="mt-3 text-sm text-stone-700">
              Once a course is published you can send its learners announcements from here.
            </p>
          </div>
        ) : (
          <>
            {published.length > 1 && (
              <div className="space-y-1.5">
                <label htmlFor="ann-course" className="text-sm font-semibold text-ink">
                  Course
                </label>
                <select
                  id="ann-course"
                  value={courseId ?? ""}
                  onChange={(e) => setPicked(e.target.value)}
                  className="h-11 w-full rounded-md border border-input bg-white px-3 text-[0.95rem]"
                >
                  {published.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.title}
                    </option>
                  ))}
                </select>
              </div>
            )}
            {course && <Composer key={course.id} courseId={course.id} courseTitle={course.title} />}
            {courseId && <History courseId={courseId} />}
          </>
        )}
      </div>
    </>
  );
}

function Composer({ courseId, courseTitle }: { courseId: string; courseTitle: string }) {
  const queryClient = useQueryClient();
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fields, setFields] = useState<Record<string, string[]>>({});
  const [sent, setSent] = useState(false);
  const text = body.replace(/<[^>]*>/g, "").trim();

  const send = async () => {
    if (!window.confirm(`Send “${title.trim()}” to everyone enrolled in ${courseTitle}? It can’t be unsent.`)) return;
    setBusy(true);
    setError(null);
    setFields({});
    try {
      await postAnnouncement(courseId, title.trim(), body);
      await queryClient.invalidateQueries({ queryKey: ["instructor", "announcements", courseId] });
      setTitle("");
      setBody("");
      setSent(true);
    } catch (e) {
      if (e instanceof ApiRequestError) {
        setError(e.message);
        setFields(e.fields);
      } else setError("Couldn’t send the announcement.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="space-y-5 rounded-md border border-line bg-white p-6">
      <div>
        <h2 className="font-sans text-lg font-bold text-ink">New announcement</h2>
        <p className="text-sm text-stone-500">
          Goes to everyone with active access by email (unless they&rsquo;ve opted out) and to their Erudsway inbox.
        </p>
      </div>
      {sent && <FormAlert tone="success">Sent. Learners will receive it over the next few minutes.</FormAlert>}
      {error && <FormAlert>{error}</FormAlert>}
      <Field label="Title" error={fields.title?.[0]}>
        {(p) => (
          <TextInput
            {...p}
            value={title}
            onChange={(e) => {
              setTitle(e.target.value);
              setSent(false);
            }}
            maxLength={160}
            placeholder="e.g. New lecture: GSTR-9C changes for FY 2025-26"
          />
        )}
      </Field>
      <Field label="Message" error={fields.body?.[0]}>
        {(p) => (
          <RichTextEditor
            id={p.id}
            aria-describedby={p["aria-describedby"]}
            value={body}
            onChange={setBody}
            minHeight={200}
          />
        )}
      </Field>
      <Button onClick={send} disabled={title.trim().length < 5 || text.length < 20 || busy}>
        <Send /> {busy ? "Sending…" : "Send announcement"}
      </Button>
    </section>
  );
}

function History({ courseId }: { courseId: string }) {
  const list = useInstructorAnnouncements(courseId);
  if (list.isPending) return <Skeleton className="h-32 w-full" />;
  if (list.isError || list.data.length === 0) return null;

  return (
    <section>
      <h2 className="mb-4 font-sans text-base font-bold text-ink">Sent</h2>
      <ul className="space-y-5">
        {list.data.map((a) => (
          <AnnouncementCard
            key={a.id}
            announcement={a}
            footer={
              <p className="mt-4 border-t border-line pt-3 text-xs text-stone-500">
                {a.sent_at ? `Delivered to ${plural(a.recipients_count, "learner")}` : "Sending…"}
              </p>
            }
          />
        ))}
      </ul>
    </section>
  );
}
