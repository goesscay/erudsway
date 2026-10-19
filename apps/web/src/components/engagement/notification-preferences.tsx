"use client";

import { FormAlert } from "@/components/forms/field";
import { Checkbox } from "@/components/ui/checkbox";
import { Skeleton } from "@/components/ui/skeleton";
import { useNotificationPreferences, type NotificationPreferences as Prefs } from "@/lib/engagement/api";

const topics: { key: keyof Prefs; label: string; hint: string; instructor?: boolean }[] = [
  {
    key: "course_announcements",
    label: "Course announcements",
    hint: "Updates instructors send to everyone in a course you’re taking.",
  },
  { key: "qa_replies", label: "Replies to my questions", hint: "When someone answers a question you asked in Q&A." },
  {
    key: "assignment_feedback",
    label: "Feedback on my assignments",
    hint: "When an instructor reviews an assignment you handed in.",
  },
  {
    key: "instructor_questions",
    label: "New questions in my courses",
    hint: "When a learner asks a question in a course you teach.",
    instructor: true,
  },
  {
    key: "instructor_reviews",
    label: "New reviews of my courses",
    hint: "When a learner rates a course you teach.",
    instructor: true,
  },
  {
    key: "instructor_submissions",
    label: "New assignment submissions",
    hint: "When a learner hands in an assignment in a course you teach.",
    instructor: true,
  },
  {
    key: "promotions",
    label: "Offers and new courses",
    hint: "Occasional emails about new courses and offers.",
  },
];

/** Opt in or out of each kind of message. Purchase receipts and security emails always send. */
export function NotificationPreferences({ instructor }: { instructor: boolean }) {
  const { query, update } = useNotificationPreferences();

  if (query.isPending) return <Skeleton className="h-48 w-full" />;
  if (query.isError) return <FormAlert>Couldn&rsquo;t load your preferences.</FormAlert>;

  return (
    <div className="space-y-4">
      <ul className="divide-y divide-line rounded-md border border-line bg-white">
        {topics
          .filter((t) => instructor || !t.instructor)
          .map((t) => (
            <li key={t.key}>
              <label className="flex cursor-pointer items-start gap-3 px-4 py-3.5">
                <Checkbox
                  className="mt-0.5"
                  checked={query.data[t.key]}
                  disabled={update.isPending}
                  onCheckedChange={(checked) => update.mutate({ [t.key]: checked === true })}
                />
                <span className="text-sm">
                  <span className="block font-semibold text-ink">{t.label}</span>
                  <span className="block text-stone-500">{t.hint}</span>
                </span>
              </label>
            </li>
          ))}
      </ul>
      {update.isError && <FormAlert>Couldn&rsquo;t save that change. Please try again.</FormAlert>}
      <p className="text-xs text-stone-500">Receipts, invoices, refunds and account-security emails are always sent.</p>
    </div>
  );
}
