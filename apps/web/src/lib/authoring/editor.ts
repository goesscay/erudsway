"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { components } from "@erudsway/api-client";
import { api, API_URL, ApiRequestError, ensureCsrfCookie, readXsrfCookie, unwrap } from "@/lib/api/client";

export type CourseEditor = components["schemas"]["CourseEditorResource"];
export type EditorSection = CourseEditor["curriculum"][number];
export type EditorLecture = EditorSection["lectures"][number];
export type ChecklistItem = CourseEditor["checklist"][number];
export type InstructorCourse = components["schemas"]["InstructorCourseResource"];

export const editorKey = (id: string) => ["course-editor", id] as const;

const inFlight = (c: CourseEditor | undefined) =>
  !!c?.curriculum.some((s) => s.lectures.some((l) => l.video && (l.video.status === "processing" || l.video.status === "uploading")));

/** The course being edited. Polls while any video is still processing. */
export function useCourseEditor(id: string) {
  return useQuery({
    queryKey: editorKey(id),
    queryFn: async () => (await unwrap(api.GET("/instructor/courses/{course}", { params: { path: { course: id } } }))).data,
    refetchInterval: (q) => (inFlight(q.state.data) ? 5000 : false),
  });
}

/**
 * Every authoring endpoint returns the updated course; this writes it straight into the
 * cache so the builder always shows the server's version.
 */
export function useEditorAction<TVars>(id: string, action: (vars: TVars) => Promise<{ data: CourseEditor }>) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (vars: TVars) => {
      await ensureCsrfCookie();
      return action(vars);
    },
    onSuccess: (res) => {
      queryClient.setQueryData(editorKey(id), res.data);
      void queryClient.invalidateQueries({ queryKey: ["instructor-courses"] });
    },
  });
}

/** Multipart upload helper (openapi-fetch is JSON-first). */
export async function postForm<T>(path: string, form: FormData): Promise<T> {
  await ensureCsrfCookie();
  const res = await fetch(`${API_URL}/api/v1${path}`, {
    method: "POST",
    body: form,
    credentials: "include",
    headers: { Accept: "application/json", "X-XSRF-TOKEN": readXsrfCookie() },
  });
  return parseResponse<T>(res);
}

export async function parseResponse<T>(res: Response): Promise<T> {
  const body = res.status === 204 ? null : await res.json().catch(() => null);
  if (!res.ok) {
    const e = (body as { error?: { code?: string; message?: string; fields?: Record<string, string[]> } } | null)?.error;
    throw new ApiRequestError(res.status, e?.code ?? "UNKNOWN", e?.message ?? "Something went wrong. Please try again.", e?.fields ?? {});
  }
  return body as T;
}

export const statusLabel: Record<string, string> = {
  draft: "Draft",
  in_review: "In review",
  changes_requested: "Changes requested",
  published: "Live",
  unpublished: "Unpublished",
  archived: "Archived",
};

export const statusStyle: Record<string, string> = {
  draft: "bg-paper-2 text-stone-700 ring-line",
  in_review: "bg-gold-50 text-gold-600 ring-gold-200",
  changes_requested: "bg-destructive/5 text-destructive ring-destructive/20",
  published: "bg-[#e7f1ec] text-success ring-[#cfe3d8]",
  unpublished: "bg-paper-2 text-stone-500 ring-line",
  archived: "bg-paper-2 text-stone-500 ring-line",
};
