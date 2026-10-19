"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { components } from "@erudsway/api-client";
import { API_URL, api, ensureCsrfCookie, mutate, readXsrfCookie, unwrap } from "@/lib/api/client";

export type LearnCourse = components["schemas"]["LearnCourseResource"];
export type LearnSection = LearnCourse["curriculum"][number];
export type LearnLecture = LearnSection["lectures"][number];
export type LectureContent = components["schemas"]["LectureContentResource"];
export type Enrollment = components["schemas"]["EnrollmentResource"];
export type Note = components["schemas"]["NoteResource"];

export const learnKey = (slug: string) => ["learn", slug] as const;
export const notesKey = (slug: string) => ["learn", slug, "notes"] as const;
export const myLearningKey = ["my-learning"] as const;

export function useLearnCourse(slug: string) {
  return useQuery({
    queryKey: learnKey(slug),
    queryFn: async () => (await unwrap(api.GET("/learn/{slug}", { params: { path: { slug } } }))).data,
    retry: false,
  });
}

export function useLectureContent(lectureId: number | null) {
  return useQuery({
    queryKey: ["lecture-content", lectureId],
    queryFn: async () => (await unwrap(api.GET("/learn/lectures/{lecture}", { params: { path: { lecture: lectureId! } } }))).data,
    enabled: lectureId !== null,
    staleTime: 5 * 60_000,
  });
}

export function useMyLearning() {
  return useQuery({
    queryKey: myLearningKey,
    queryFn: async () => (await unwrap(api.GET("/me/enrollments"))).data,
  });
}

export function useEnrollmentStatus(slug: string, enabled: boolean) {
  return useQuery({
    queryKey: ["enrollment-status", slug],
    queryFn: async () => (await unwrap(api.GET("/me/courses/{slug}/enrollment", { params: { path: { slug } } }))).data,
    enabled,
  });
}

/** Starts (or refreshes, after expiry) playback of a video lecture. */
export async function requestPlayback(lectureId: number) {
  await ensureCsrfCookie();
  return (await unwrap(api.POST("/lectures/{lecture}/playback", { params: { path: { lecture: lectureId } } }))).data;
}

export type Playback = Awaited<ReturnType<typeof requestPlayback>>;

/** Merge a lecture's new progress into the cached outline so the sidebar and ring update at once. */
function applyProgress(
  course: LearnCourse | undefined,
  lectureId: number,
  patch: { completed: boolean; last_position_seconds: number; enrollment: { progress_percent: number; completed_count: number } | null },
): LearnCourse | undefined {
  if (!course) return course;
  return {
    ...course,
    enrollment:
      course.enrollment && patch.enrollment
        ? { ...course.enrollment, ...patch.enrollment, last_lecture_id: lectureId }
        : course.enrollment,
    curriculum: course.curriculum.map((s) => ({
      ...s,
      lectures: s.lectures.map((l) =>
        l.id === lectureId ? { ...l, completed: patch.completed, last_position_seconds: patch.last_position_seconds } : l,
      ),
    })),
  };
}

export function useProgress(slug: string) {
  const queryClient = useQueryClient();
  const apply = (lectureId: number, data: Parameters<typeof applyProgress>[2] & { tracked: boolean }) => {
    if (data.tracked) queryClient.setQueryData<LearnCourse>(learnKey(slug), (c) => applyProgress(c, lectureId, data));
    void queryClient.invalidateQueries({ queryKey: myLearningKey });
  };

  return {
    async savePosition(lectureId: number, position: number) {
      const res = await mutate(() =>
        api.PUT("/lectures/{lecture}/progress", { params: { path: { lecture: lectureId } }, body: { position_seconds: Math.floor(position) } }),
      );
      apply(lectureId, res.data);
    },
    /** For page unload: fire-and-forget, survives navigation. */
    beaconPosition(lectureId: number, position: number) {
      void fetch(`${API_URL}/api/v1/lectures/${lectureId}/progress`, {
        method: "PUT",
        keepalive: true,
        credentials: "include",
        headers: { Accept: "application/json", "Content-Type": "application/json", "X-XSRF-TOKEN": readXsrfCookie() },
        body: JSON.stringify({ position_seconds: Math.floor(position) }),
      }).catch(() => {});
    },
    async setCompleted(lectureId: number, completed: boolean) {
      const path = { params: { path: { lecture: lectureId } } };
      const res = await mutate(() => (completed ? api.POST("/lectures/{lecture}/completion", path) : api.DELETE("/lectures/{lecture}/completion", path)));
      apply(lectureId, res.data);
    },
  };
}

export function useNotes(slug: string) {
  return useQuery({
    queryKey: notesKey(slug),
    queryFn: async () => (await unwrap(api.GET("/learn/{slug}/notes", { params: { path: { slug } } }))).data,
  });
}

export function useNoteActions(slug: string) {
  const queryClient = useQueryClient();
  const refresh = () => queryClient.invalidateQueries({ queryKey: notesKey(slug) });

  return {
    create: useMutation({
      mutationFn: (vars: { lectureId: number; body: string; at_seconds: number }) =>
        mutate(() => api.POST("/lectures/{lecture}/notes", { params: { path: { lecture: vars.lectureId } }, body: { body: vars.body, at_seconds: vars.at_seconds } })),
      onSuccess: refresh,
    }),
    update: useMutation({
      mutationFn: (vars: { id: number; body: string }) => mutate(() => api.PATCH("/notes/{note}", { params: { path: { note: vars.id } }, body: { body: vars.body } })),
      onSuccess: refresh,
    }),
    remove: useMutation({
      mutationFn: (id: number) => mutate(() => api.DELETE("/notes/{note}", { params: { path: { note: id } } })),
      onSuccess: refresh,
    }),
  };
}

/** Flat lecture list in course order, with each lecture's number (1-based, across sections). */
export function flattenLectures(course: LearnCourse): (LearnLecture & { number: number; sectionTitle: string })[] {
  let n = 0;
  return course.curriculum.flatMap((s) => s.lectures.map((l) => ({ ...l, number: ++n, sectionTitle: s.title })));
}

/** Where to start: the lecture the learner last opened, else the first unfinished one, else the first. */
export function resumeLectureId(course: LearnCourse): number | null {
  const all = flattenLectures(course);
  const last = course.enrollment?.last_lecture_id;
  if (last && all.some((l) => l.id === last)) return last;
  return (all.find((l) => !l.completed) ?? all[0])?.id ?? null;
}
