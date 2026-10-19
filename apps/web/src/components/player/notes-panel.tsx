"use client";

import { useEffect, useState } from "react";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { ApiRequestError } from "@/lib/api/client";
import { useNoteActions, useNotes, type Note } from "@/lib/learning/api";
import { formatClock } from "@/lib/format";

/**
 * Private notes pinned to a moment in a lecture. Clicking a note's time jumps there.
 */
export function NotesPanel({
  slug,
  lecture,
  numberOf,
  currentTime,
  onJump,
}: {
  slug: string;
  lecture: { id: number; title: string; kind: string } | null;
  /** Lecture number shown next to each note. */
  numberOf: (lectureId: number) => number | undefined;
  /** Reads the player's position at the moment a note is saved. */
  currentTime: () => number;
  onJump: (lectureId: number, seconds: number) => void;
}) {
  const notes = useNotes(slug);
  const { create, update, remove } = useNoteActions(slug);
  const [draft, setDraft] = useState("");
  const [writing, setWriting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const isVideo = lecture?.kind === "video";

  const save = () => {
    if (!lecture || !draft.trim()) return;
    setError(null);
    create.mutate(
      { lectureId: lecture.id, body: draft.trim(), at_seconds: isVideo ? Math.floor(currentTime()) : 0 },
      {
        onSuccess: () => {
          setDraft("");
          setWriting(false);
        },
        onError: (e) => setError(e instanceof ApiRequestError ? e.message : "Couldn’t save the note."),
      },
    );
  };

  const sorted = [...(notes.data ?? [])].sort((a, b) => (numberOf(a.lecture_id) ?? 0) - (numberOf(b.lecture_id) ?? 0) || a.at_seconds - b.at_seconds);

  return (
    <div className="space-y-6">
      {lecture &&
        (writing ? (
          <div className="space-y-3 rounded-md border border-line bg-white p-4">
            <label htmlFor="note-body" className="text-sm font-semibold text-ink">
              Note on “{lecture.title}”{isVideo && <> at {formatClock(Math.floor(currentTime()))}</>}
            </label>
            {error && <FormAlert>{error}</FormAlert>}
            <Textarea id="note-body" rows={4} maxLength={2000} value={draft} onChange={(e) => setDraft(e.target.value)} autoFocus className="bg-white" />
            <div className="flex gap-2">
              <Button onClick={save} disabled={!draft.trim() || create.isPending}>
                {create.isPending ? "Saving…" : "Save note"}
              </Button>
              <Button variant="outline" onClick={() => setWriting(false)}>
                Cancel
              </Button>
            </div>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => setWriting(true)}
            className="flex w-full items-center justify-between rounded-md border border-line bg-white px-4 py-3 text-left text-sm text-stone-500 hover:border-input"
          >
            <span>
              Create a new note{isVideo && <> at <LiveTime read={currentTime} /></>}
            </span>
            <Plus className="size-4 text-navy-800" />
          </button>
        ))}

      {notes.isPending ? (
        <Skeleton className="h-24 w-full" />
      ) : sorted.length === 0 ? (
        <p className="py-6 text-center text-sm text-stone-500">Notes you take are private to you and saved at the current video time.</p>
      ) : (
        <ul className="space-y-4">
          {sorted.map((note) => (
            <NoteItem
              key={note.id}
              note={note}
              number={numberOf(note.lecture_id)}
              onJump={() => onJump(note.lecture_id, note.at_seconds)}
              onSave={(body) => update.mutateAsync({ id: note.id, body })}
              onDelete={() => remove.mutate(note.id)}
            />
          ))}
        </ul>
      )}
    </div>
  );
}

/** The player's position, refreshed every second while the panel is open. */
function LiveTime({ read }: { read: () => number }) {
  const [, tick] = useState(0);
  useEffect(() => {
    const id = setInterval(() => tick((n) => n + 1), 1000);
    return () => clearInterval(id);
  }, []);
  return <span className="tabular">{formatClock(Math.floor(read()))}</span>;
}

function NoteItem({
  note,
  number,
  onJump,
  onSave,
  onDelete,
}: {
  note: Note;
  number: number | undefined;
  onJump: () => void;
  onSave: (body: string) => Promise<unknown>;
  onDelete: () => void;
}) {
  const [editing, setEditing] = useState(false);
  const [body, setBody] = useState(note.body);

  return (
    <li className="flex gap-4">
      <button
        type="button"
        onClick={onJump}
        className="tabular h-6 shrink-0 rounded-full bg-navy-900 px-2.5 text-xs font-semibold text-white hover:bg-navy-800"
        aria-label={`Go to ${formatClock(note.at_seconds)} in lecture ${number ?? ""}`}
      >
        {formatClock(note.at_seconds)}
      </button>
      <div className="min-w-0 flex-1">
        <div className="flex items-start gap-2">
          <p className="flex-1 text-sm font-semibold text-ink">
            {number !== undefined && <span className="text-stone-500">{number}. </span>}
            {note.lecture_title}
          </p>
          {!editing && (
            <>
              <button type="button" onClick={() => setEditing(true)} aria-label="Edit note" className="rounded-sm p-1 text-stone-500 hover:text-navy-900">
                <Pencil className="size-3.5" />
              </button>
              <button
                type="button"
                onClick={() => window.confirm("Delete this note?") && onDelete()}
                aria-label="Delete note"
                className="rounded-sm p-1 text-stone-500 hover:text-destructive"
              >
                <Trash2 className="size-3.5" />
              </button>
            </>
          )}
        </div>
        {editing ? (
          <div className="mt-2 space-y-2">
            <Textarea rows={3} maxLength={2000} value={body} onChange={(e) => setBody(e.target.value)} aria-label="Note" className="bg-white" />
            <div className="flex gap-2">
              <Button size="sm" disabled={!body.trim()} onClick={() => void onSave(body.trim()).then(() => setEditing(false))}>
                Save
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => {
                  setBody(note.body);
                  setEditing(false);
                }}
              >
                Cancel
              </Button>
            </div>
          </div>
        ) : (
          <p className="mt-1 rounded-md bg-paper-2/70 px-4 py-3 text-sm whitespace-pre-line text-stone-700">{note.body}</p>
        )}
      </div>
    </li>
  );
}
