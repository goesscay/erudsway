"use client";

import { useRef } from "react";
import { FileText, ImageIcon, Lock, Paperclip, X } from "lucide-react";
import { ACCEPT, attachmentUrl, formatBytes, MAX_FILES, type TicketMessage } from "@/lib/help/api";
import { cn } from "@/lib/utils";

const statusStyles: Record<string, string> = {
  open: "bg-gold-50 text-gold-600 ring-gold-200",
  in_progress: "bg-navy-100 text-navy-800 ring-navy-200",
  waiting_learner: "bg-[#fdeee6] text-[#a4471b] ring-[#f5d3c0]",
  resolved: "bg-[#e7f1ec] text-success ring-[#cfe3d8]",
  closed: "bg-paper-2 text-stone-700 ring-line",
};

export function TicketStatus({ status, label }: { status: string; label: string }) {
  return (
    <span
      className={cn(
        "inline-flex h-6 items-center rounded-sm px-2 text-[0.7rem] font-bold tracking-wide whitespace-nowrap ring-1 ring-inset",
        statusStyles[status] ?? statusStyles.closed,
      )}
    >
      {label}
    </span>
  );
}

export function formatDateTime(iso: string | null | undefined) {
  if (!iso) return "—";
  return new Date(iso).toLocaleString("en-IN", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

/** The conversation, oldest first. Staff see internal notes highlighted. */
export function Conversation({ messages }: { messages: TicketMessage[] }) {
  return (
    <ol className="space-y-4">
      {messages.map((m) => (
        <li
          key={m.id}
          className={cn(
            "rounded-md border p-4 sm:p-5",
            m.internal
              ? "border-dashed border-gold-400 bg-gold-50"
              : m.from_staff
                ? "border-navy-200 bg-navy-100/40"
                : "border-line bg-white",
          )}
        >
          <p className="flex flex-wrap items-center gap-2 text-sm">
            <strong className="text-ink">{m.author}</strong>
            {m.internal && (
              <span className="inline-flex items-center gap-1 rounded-sm bg-gold-200 px-1.5 py-0.5 text-[0.7rem] font-bold text-navy-950">
                <Lock className="size-3" aria-hidden /> Internal note
              </span>
            )}
            <span className="text-stone-500">{formatDateTime(m.created_at)}</span>
          </p>
          <p className="mt-2 text-[0.95rem] leading-relaxed whitespace-pre-line text-stone-800">{m.body}</p>
          {m.attachments.length > 0 && (
            <ul className="mt-3 flex flex-wrap gap-2">
              {m.attachments.map((a) => (
                <li key={a.id}>
                  <a
                    href={attachmentUrl(a.id)}
                    className="inline-flex items-center gap-1.5 rounded-md border border-line bg-white px-2.5 py-1.5 text-sm text-navy-800 hover:border-navy-800"
                  >
                    {a.mime === "application/pdf" ? (
                      <FileText className="size-4" aria-hidden />
                    ) : (
                      <ImageIcon className="size-4" aria-hidden />
                    )}
                    <span className="max-w-[16rem] truncate">{a.name}</span>
                    <span className="text-xs text-stone-500">{formatBytes(a.size)}</span>
                  </a>
                </li>
              ))}
            </ul>
          )}
        </li>
      ))}
    </ol>
  );
}

/** Pick up to three PNG/JPG/PDF files; shows the chosen ones with a remove button. */
export function AttachmentPicker({ files, onChange }: { files: File[]; onChange: (files: File[]) => void }) {
  const input = useRef<HTMLInputElement>(null);

  return (
    <div>
      <input
        ref={input}
        type="file"
        multiple
        accept={ACCEPT}
        className="sr-only"
        tabIndex={-1}
        aria-hidden
        onChange={(e) => {
          onChange([...files, ...Array.from(e.target.files ?? [])]);
          e.target.value = "";
        }}
      />
      <button
        type="button"
        onClick={() => input.current?.click()}
        disabled={files.length >= MAX_FILES}
        className="inline-flex items-center gap-2 rounded-md border border-dashed border-line bg-white px-3 py-2 text-sm font-semibold text-navy-800 hover:border-navy-800 disabled:opacity-50"
      >
        <Paperclip className="size-4" aria-hidden /> Attach screenshots or PDFs
      </button>
      <p className="mt-1.5 text-xs text-stone-500">PNG, JPG or PDF · up to 3 files · 5 MB each</p>
      {files.length > 0 && (
        <ul className="mt-2 flex flex-wrap gap-2">
          {files.map((f, i) => (
            <li
              key={`${f.name}-${i}`}
              className="inline-flex items-center gap-1.5 rounded-md bg-paper-2 py-1 pr-1 pl-2.5 text-sm text-stone-800"
            >
              <span className="max-w-[14rem] truncate">{f.name}</span>
              <span className="text-xs text-stone-500">{formatBytes(f.size)}</span>
              <button
                type="button"
                onClick={() => onChange(files.filter((_, j) => j !== i))}
                className="grid size-6 place-items-center rounded hover:bg-white"
                aria-label={`Remove ${f.name}`}
              >
                <X className="size-3.5" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
