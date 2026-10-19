"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { Bell, Megaphone, MessageSquare, Star } from "lucide-react";
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { useNotificationActions, useNotifications } from "@/lib/engagement/api";
import { timeAgo } from "@/lib/format";
import { cn } from "@/lib/utils";

const icons: Record<string, typeof Bell> = {
  course_announcements: Megaphone,
  qa_replies: MessageSquare,
  instructor_questions: MessageSquare,
  instructor_reviews: Star,
};

/** The header inbox: latest 20 notifications, refreshed every minute while the tab is open. */
export function NotificationBell({ className, inverted }: { className?: string; inverted?: boolean }) {
  const router = useRouter();
  const inbox = useNotifications(true);
  const { read, readAll } = useNotificationActions();
  const unread = inbox.data?.unread ?? 0;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label={unread > 0 ? `Notifications, ${unread} unread` : "Notifications"}
        className={cn(
          "relative flex size-10 items-center justify-center rounded-md outline-none focus-visible:ring-2 focus-visible:ring-gold-500",
          inverted
            ? "text-white/80 hover:bg-white/10 hover:text-white"
            : "text-stone-700 hover:bg-paper-2 hover:text-navy-900",
          className,
        )}
      >
        <Bell className="size-5" strokeWidth={1.75} />
        {unread > 0 && (
          <span className="tabular absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-gold-500 px-1 text-[0.6rem] font-bold text-navy-950">
            {unread > 9 ? "9+" : unread}
          </span>
        )}
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-[min(380px,calc(100vw-2rem))] p-0">
        <div className="flex items-center justify-between border-b border-line px-4 py-3">
          <p className="text-sm font-bold text-ink">Notifications</p>
          {unread > 0 && (
            <button
              type="button"
              onClick={() => readAll.mutate()}
              className="text-xs font-semibold text-navy-800 hover:underline"
            >
              Mark all as read
            </button>
          )}
        </div>
        {!inbox.data || inbox.data.items.length === 0 ? (
          <p className="px-4 py-10 text-center text-sm text-stone-500">
            {inbox.isPending ? "Loading…" : "You’re all caught up."}
          </p>
        ) : (
          <ul className="max-h-[420px] divide-y divide-line overflow-y-auto">
            {inbox.data.items.map((n) => {
              const Icon = icons[n.kind] ?? Bell;
              return (
                <li key={n.id}>
                  <button
                    type="button"
                    onClick={() => {
                      if (!n.read) read.mutate(n.id);
                      router.push(n.url);
                    }}
                    className={cn("flex w-full gap-3 px-4 py-3 text-left hover:bg-paper", !n.read && "bg-gold-50/50")}
                  >
                    <Icon className="mt-0.5 size-4 shrink-0 text-gold-600" />
                    <span className="min-w-0 flex-1">
                      <span className={cn("block text-sm text-ink", !n.read && "font-semibold")}>{n.title}</span>
                      <span className="mt-0.5 line-clamp-2 block text-xs text-stone-500">{n.body}</span>
                      <span className="mt-1 block text-[0.7rem] text-stone-500">{timeAgo(n.created_at)}</span>
                    </span>
                    {!n.read && (
                      <span className="mt-1.5 size-2 shrink-0 rounded-full bg-gold-500" aria-label="Unread" />
                    )}
                  </button>
                </li>
              );
            })}
          </ul>
        )}
        <div className="border-t border-line px-4 py-2.5 text-right">
          <Link href="/account#notifications" className="text-xs font-semibold text-navy-800 hover:underline">
            Notification settings
          </Link>
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
