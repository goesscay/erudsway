"use client";

import { useRef, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { CircleAlert, FileDown, Loader2, Paperclip, Trash2, Upload, Video } from "lucide-react";
import { RichTextEditor } from "@/components/authoring/rich-text-editor";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { api, API_URL, ApiRequestError, unwrap } from "@/lib/api/client";
import { editorKey, postForm, useEditorAction, type CourseEditor, type EditorLecture } from "@/lib/authoring/editor";
import { uploadVideo } from "@/lib/authoring/uploader";
import { formatClock } from "@/lib/format";

const formatBytes = (n: number) =>
  n >= 1024 ** 3
    ? `${(n / 1024 ** 3).toFixed(1)} GB`
    : n >= 1024 ** 2
      ? `${(n / 1024 ** 2).toFixed(1)} MB`
      : `${Math.max(1, Math.round(n / 1024))} KB`;

/** Upload, progress, processing and failure states for a video lecture. */
export function VideoPanel({
  courseId,
  lecture,
  locked,
}: {
  courseId: string;
  lecture: EditorLecture;
  locked: boolean;
}) {
  const input = useRef<HTMLInputElement>(null);
  const queryClient = useQueryClient();
  const [progress, setProgress] = useState<{ sent: number; total: number } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const abort = useRef<AbortController | null>(null);
  const video = lecture.video;

  const start = async (file: File) => {
    setError(null);
    abort.current = new AbortController();
    setProgress({ sent: 0, total: file.size });
    try {
      await uploadVideo(
        lecture.id,
        file,
        (p) => setProgress({ sent: p.sentBytes, total: p.totalBytes }),
        abort.current.signal,
      );
    } catch (e) {
      if (!abort.current.signal.aborted) {
        setError(
          e instanceof ApiRequestError
            ? (Object.values(e.fields)[0]?.[0] ?? e.message)
            : "The upload failed. Choose the file again to resume.",
        );
      }
    } finally {
      setProgress(null);
      await queryClient.invalidateQueries({ queryKey: editorKey(courseId) });
    }
  };

  const pct = progress ? Math.floor((progress.sent / Math.max(1, progress.total)) * 100) : 0;

  return (
    <div className="space-y-3">
      {error && <FormAlert>{error}</FormAlert>}
      <input
        ref={input}
        type="file"
        accept="video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo"
        className="sr-only"
        onChange={(e) => {
          const file = e.target.files?.[0];
          e.target.value = "";
          if (file) void start(file);
        }}
      />

      {progress ? (
        <div className="rounded-md border border-line bg-white p-4">
          <div className="flex items-center justify-between text-sm">
            <span className="font-semibold text-ink">Uploading…</span>
            <span className="tabular text-stone-500">
              {formatBytes(progress.sent)} of {formatBytes(progress.total)} · {pct}%
            </span>
          </div>
          <Progress value={pct} className="mt-2 h-1.5" aria-label={`Upload ${pct}% complete`} />
          <div className="mt-3 flex items-center justify-between">
            <p className="text-xs text-stone-500">
              Keep this tab open. If it closes, choose the same file again to resume.
            </p>
            <Button type="button" size="sm" variant="ghost" onClick={() => abort.current?.abort()}>
              Pause
            </Button>
          </div>
        </div>
      ) : video?.status === "processing" || video?.status === "uploading" ? (
        <div className="flex items-center gap-3 rounded-md border border-gold-200 bg-gold-50 p-4 text-sm text-stone-700">
          <Loader2 className="size-4 animate-spin text-gold-600" />
          <span>
            <strong className="text-ink">Processing {video.original_name}</strong> — preparing HD and mobile versions.
            You can keep working; this updates automatically.
          </span>
        </div>
      ) : video?.status === "failed" ? (
        <div className="flex items-start gap-3 rounded-md border border-destructive/20 bg-destructive/5 p-4 text-sm">
          <CircleAlert className="mt-0.5 size-4 shrink-0 text-destructive" />
          <div className="flex-1">
            <p className="font-semibold text-destructive">We couldn&rsquo;t process {video.original_name}</p>
            {video.failure_reason && <p className="mt-0.5 text-stone-700">{video.failure_reason}</p>}
          </div>
          <Button type="button" size="sm" variant="outline" disabled={locked} onClick={() => input.current?.click()}>
            Upload again
          </Button>
        </div>
      ) : video?.status === "ready" ? (
        <div className="flex items-center gap-3 rounded-md border border-line bg-white p-4 text-sm">
          <Video className="size-4 text-success" />
          <span className="flex-1 truncate text-stone-700">
            <strong className="text-ink">{video.original_name}</strong> · {formatClock(video.duration_seconds)}
          </span>
          <Button type="button" size="sm" variant="outline" disabled={locked} onClick={() => input.current?.click()}>
            Replace
          </Button>
        </div>
      ) : (
        <button
          type="button"
          disabled={locked}
          onClick={() => input.current?.click()}
          className="flex w-full flex-col items-center gap-1.5 rounded-md border border-dashed border-input bg-white px-4 py-7 text-sm text-stone-700 hover:border-navy-900 hover:bg-navy-100/30 disabled:opacity-60"
        >
          <Upload className="size-5 text-navy-800" />
          <span className="font-semibold text-navy-900">Upload video</span>
          <span className="text-xs text-stone-500">
            MP4, MOV, MKV, WebM or AVI · up to 4 GB · 720p or 1080p recommended
          </span>
        </button>
      )}
    </div>
  );
}

/** Rich-text body for an article lecture. */
export function ArticlePanel({
  courseId,
  lecture,
  locked,
}: {
  courseId: string;
  lecture: EditorLecture;
  locked: boolean;
}) {
  const [html, setHtml] = useState(lecture.article_html ?? "");
  const [baseline, setBaseline] = useState(lecture.article_html ?? "");
  const [error, setError] = useState<string | null>(null);
  const save = useEditorAction(courseId, () =>
    unwrap(
      api.PATCH("/instructor/lectures/{lecture}", {
        params: { path: { lecture: lecture.id } },
        body: { article_html: html || null },
      }),
    ),
  );

  return (
    <div className="space-y-3">
      {error && <FormAlert>{error}</FormAlert>}
      <RichTextEditor value={html} onChange={setHtml} minHeight={220} media />
      <div className="flex items-center gap-3">
        <Button
          type="button"
          size="sm"
          disabled={locked || html === baseline || save.isPending}
          onClick={() =>
            save.mutate(undefined, {
              onSuccess: () => setBaseline(html),
              onError: (e) => setError(e instanceof ApiRequestError ? e.message : "Couldn’t save."),
            })
          }
        >
          {save.isPending ? "Saving…" : "Save article"}
        </Button>
        {html !== baseline && <span className="text-xs text-stone-500">Unsaved changes</span>}
      </div>
    </div>
  );
}

/** Downloadable files attached to a lecture. */
export function ResourcesPanel({
  courseId,
  lecture,
  locked,
  editions = [],
}: {
  courseId: string;
  lecture: EditorLecture;
  locked: boolean;
  editions?: CourseEditor["editions"];
}) {
  const input = useRef<HTMLInputElement>(null);
  const [error, setError] = useState<string | null>(null);
  const upload = useEditorAction(courseId, (file: File) => {
    const form = new FormData();
    form.append("file", file);
    return postForm<{ data: CourseEditor }>(`/instructor/lectures/${lecture.id}/resources`, form);
  });
  const remove = useEditorAction(courseId, (id: number) =>
    unwrap(api.DELETE("/instructor/resources/{resource}", { params: { path: { resource: id } } })),
  );
  const setEdition = useEditorAction(courseId, ({ id, edition }: { id: number; edition: number | null }) =>
    unwrap(api.PUT("/instructor/resources/{resource}/edition", { params: { path: { resource: id } }, body: { min_edition_id: edition } })),
  );

  return (
    <div>
      <p className="mb-2 text-xs font-semibold tracking-wide text-stone-500 uppercase">Downloadable resources</p>
      {error && (
        <div className="mb-2">
          <FormAlert>{error}</FormAlert>
        </div>
      )}
      {lecture.resources.length > 0 && (
        <ul className="mb-2 divide-y divide-line rounded-md border border-line bg-white">
          {lecture.resources.map((r) => (
            <li key={r.id} className="flex items-center gap-3 px-3 py-2 text-sm">
              <FileDown className="size-4 text-stone-500" />
              <a
                href={`${API_URL}/api/v1/instructor/resources/${r.id}/download`}
                className="flex-1 truncate text-navy-800 hover:underline"
              >
                {r.name}
              </a>
              <span className="tabular text-xs text-stone-500">{formatBytes(r.size_bytes)}</span>
              {editions.length > 1 && (
                <select
                  aria-label={`Which editions get ${r.name}`}
                  value={r.min_edition_id ?? ""}
                  disabled={locked || setEdition.isPending}
                  onChange={(e) => setEdition.mutate({ id: r.id, edition: e.target.value ? Number(e.target.value) : null })}
                  className="h-7 rounded-sm border border-input bg-white px-1.5 text-xs"
                >
                  <option value="">All editions</option>
                  {editions.slice(1).map((ed) => (
                    <option key={ed.id} value={ed.id}>
                      {ed.name}+
                    </option>
                  ))}
                </select>
              )}
              <button
                type="button"
                aria-label={`Remove ${r.name}`}
                disabled={locked}
                onClick={() => remove.mutate(r.id)}
                className="rounded-sm p-1 text-stone-500 hover:text-destructive disabled:opacity-40"
              >
                <Trash2 className="size-3.5" />
              </button>
            </li>
          ))}
        </ul>
      )}
      <input
        ref={input}
        type="file"
        accept=".pdf,.xlsx,.xls,.csv,.docx,.doc,.pptx,.zip,.txt"
        className="sr-only"
        onChange={(e) => {
          const file = e.target.files?.[0];
          e.target.value = "";
          if (!file) return;
          setError(null);
          upload.mutate(file, {
            onError: (err) =>
              setError(
                err instanceof ApiRequestError ? (Object.values(err.fields)[0]?.[0] ?? err.message) : "Upload failed.",
              ),
          });
        }}
      />
      <Button
        type="button"
        size="sm"
        variant="ghost"
        className="text-navy-800"
        disabled={locked || upload.isPending}
        onClick={() => input.current?.click()}
      >
        <Paperclip /> {upload.isPending ? "Uploading…" : "Add a file"}
      </Button>
      <span className="ml-2 text-xs text-stone-500">PDF, Excel, Word, PowerPoint, CSV or ZIP · up to 50 MB</span>
    </div>
  );
}
