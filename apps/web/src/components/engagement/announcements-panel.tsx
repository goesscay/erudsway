"use client";

import { Megaphone } from "lucide-react";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { FormAlert } from "@/components/forms/field";
import { Skeleton } from "@/components/ui/skeleton";
import { useCourseAnnouncements, type Announcement } from "@/lib/engagement/api";
import { timeAgo } from "@/lib/format";

export function AnnouncementsPanel({ slug }: { slug: string }) {
  const list = useCourseAnnouncements(slug);

  if (list.isPending) return <Skeleton className="h-40 w-full" />;
  if (list.isError) return <FormAlert>Couldn&rsquo;t load announcements.</FormAlert>;
  if (list.data.length === 0) {
    return (
      <div className="rounded-md border border-dashed border-line bg-white px-6 py-10 text-center">
        <Megaphone className="mx-auto size-8 text-stone-300" strokeWidth={1.5} />
        <p className="mt-3 text-sm text-stone-700">
          No announcements yet. When the instructor posts an update, it appears here and in your inbox.
        </p>
      </div>
    );
  }

  return (
    <ul className="space-y-5">
      {list.data.map((a) => (
        <AnnouncementCard key={a.id} announcement={a} />
      ))}
    </ul>
  );
}

export function AnnouncementCard({
  announcement: a,
  footer,
}: {
  announcement: Announcement;
  footer?: React.ReactNode;
}) {
  return (
    <li className="rounded-md border border-line bg-white p-5">
      <div className="flex items-center gap-3">
        <InstructorAvatar name={a.author.name} className="size-10 text-xs" />
        <div className="text-sm">
          <p className="font-bold text-ink">{a.author.name}</p>
          <p className="text-xs text-stone-500">Posted an announcement · {timeAgo(a.created_at)}</p>
        </div>
      </div>
      <h3 className="mt-4 font-sans text-lg font-bold text-ink">{a.title}</h3>
      {/* Sanitised server-side (HTMLPurifier allowlist) when posted. */}
      <div
        className="prose-content mt-2 text-sm leading-relaxed text-stone-700"
        dangerouslySetInnerHTML={{ __html: a.body_html }}
      />
      {footer}
    </li>
  );
}
