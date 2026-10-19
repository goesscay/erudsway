"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { components } from "@erudsway/api-client";
import { api, mutate, unwrap } from "@/lib/api/client";

export type Review = components["schemas"]["ReviewResource"];
export type Question = components["schemas"]["QuestionResource"];
export type Answer = NonNullable<Question["answers"]>[number];
export type Announcement = components["schemas"]["AnnouncementResource"];

/** Laravel's boolean rule wants 1, not the "true" a query string would carry. */
const flag = (on: boolean | undefined) => (on ? (1 as unknown as boolean) : undefined);

/* ---------------------------------------------------------------- Reviews */

export function useCourseReviews(slug: string, rating: number | null, page: number) {
  return useQuery({
    queryKey: ["reviews", slug, rating, page],
    queryFn: async () =>
      unwrap(
        api.GET("/courses/{slug}/reviews", {
          params: { path: { slug }, query: { rating: rating ?? undefined, page } },
        }),
      ),
    placeholderData: keepPreviousData,
  });
}

export function useMyReview(slug: string, enabled = true) {
  return useQuery({
    queryKey: ["my-review", slug],
    queryFn: async () =>
      (await unwrap(api.GET("/courses/{slug}/review", { params: { path: { slug } } }))).data as Review | null,
    enabled,
  });
}

export function useReviewActions(slug: string) {
  const queryClient = useQueryClient();
  const refresh = () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: ["my-review", slug] }),
      queryClient.invalidateQueries({ queryKey: ["reviews", slug] }),
    ]);

  const save = useMutation({
    mutationFn: (body: { rating: number; body: string | null }) =>
      mutate(() => api.PUT("/courses/{slug}/review", { params: { path: { slug } }, body })),
    onSuccess: refresh,
  });
  const remove = useMutation({
    mutationFn: () => mutate(() => api.DELETE("/courses/{slug}/review", { params: { path: { slug } } })),
    onSuccess: refresh,
  });
  return { save, remove };
}

export function reportReview(id: number, reason: string) {
  return mutate(() => api.POST("/reviews/{review}/report", { params: { path: { review: id } }, body: { reason } }));
}

export function useInstructorReviews(unreplied: boolean, page: number) {
  return useQuery({
    queryKey: ["instructor", "reviews", unreplied, page],
    queryFn: async () =>
      unwrap(api.GET("/instructor/reviews", { params: { query: { unreplied: flag(unreplied), page } } })),
    placeholderData: keepPreviousData,
  });
}

export function replyToReview(id: number, reply: string | null) {
  return mutate(() =>
    api.PUT("/instructor/reviews/{review}/reply", { params: { path: { review: id } }, body: { reply } }),
  );
}

export function useModerationQueue(status: "flagged" | "hidden" | "visible") {
  return useQuery({
    queryKey: ["admin", "reviews", status],
    queryFn: async () => unwrap(api.GET("/admin/reviews", { params: { query: { status } } })),
  });
}

export function moderateReview(id: number, action: "hide" | "restore", note: string | null) {
  return mutate(() =>
    api.POST("/admin/reviews/{review}/moderate", { params: { path: { review: id } }, body: { action, note } }),
  );
}

/* -------------------------------------------------------------------- Q&A */

export type QuestionFilters = { lecture?: number; q?: string; mine?: boolean; page?: number };

export function useQuestions(slug: string, filters: QuestionFilters) {
  return useQuery({
    queryKey: ["questions", slug, filters],
    queryFn: async () =>
      unwrap(
        api.GET("/learn/{slug}/questions", {
          params: {
            path: { slug },
            query: {
              lecture: filters.lecture,
              q: filters.q || undefined,
              mine: flag(filters.mine),
              page: filters.page,
            },
          },
        }),
      ),
    placeholderData: keepPreviousData,
  });
}

export function useQuestion(id: number | null) {
  return useQuery({
    queryKey: ["question", id],
    queryFn: async () => (await unwrap(api.GET("/questions/{question}", { params: { path: { question: id! } } }))).data,
    enabled: id !== null,
    retry: false,
  });
}

export function useQaActions(slug?: string) {
  const queryClient = useQueryClient();
  const refresh = (id?: number) =>
    Promise.all([
      slug ? queryClient.invalidateQueries({ queryKey: ["questions", slug] }) : null,
      id ? queryClient.invalidateQueries({ queryKey: ["question", id] }) : null,
      queryClient.invalidateQueries({ queryKey: ["instructor", "questions"] }),
    ]);

  const ask = useMutation({
    mutationFn: (body: { title: string; body: string; lecture_id: number | null }) =>
      mutate(() => api.POST("/learn/{slug}/questions", { params: { path: { slug: slug! } }, body })),
    onSuccess: () => refresh(),
  });
  const answer = useMutation({
    mutationFn: ({ id, body }: { id: number; body: string }) =>
      mutate(() => api.POST("/questions/{question}/answers", { params: { path: { question: id } }, body: { body } })),
    onSuccess: (_, v) => refresh(v.id),
  });
  const hideQuestion = useMutation({
    mutationFn: ({ id, hidden }: { id: number; hidden: boolean }) =>
      mutate(() => api.POST("/questions/{question}/hide", { params: { path: { question: id } }, body: { hidden } })),
    onSuccess: (_, v) => refresh(v.id),
  });
  const hideAnswer = useMutation({
    mutationFn: ({ id, hidden }: { id: number; questionId: number; hidden: boolean }) =>
      mutate(() => api.POST("/answers/{answer}/hide", { params: { path: { answer: id } }, body: { hidden } })),
    onSuccess: (_, v) => refresh(v.questionId),
  });
  return { ask, answer, hideQuestion, hideAnswer };
}

export function useInstructorQuestions(unanswered: boolean, page: number) {
  return useQuery({
    queryKey: ["instructor", "questions", unanswered, page],
    queryFn: async () =>
      unwrap(api.GET("/instructor/questions", { params: { query: { unanswered: flag(unanswered), page } } })),
    placeholderData: keepPreviousData,
  });
}

/* ---------------------------------------------------------- Announcements */

export function useCourseAnnouncements(slug: string) {
  return useQuery({
    queryKey: ["announcements", slug],
    queryFn: async () => (await unwrap(api.GET("/learn/{slug}/announcements", { params: { path: { slug } } }))).data,
  });
}

export function useInstructorAnnouncements(courseUuid: string | null) {
  return useQuery({
    queryKey: ["instructor", "announcements", courseUuid],
    queryFn: async () =>
      (
        await unwrap(
          api.GET("/instructor/courses/{course}/announcements", { params: { path: { course: courseUuid! } } }),
        )
      ).data,
    enabled: !!courseUuid,
  });
}

export function postAnnouncement(courseUuid: string, title: string, body: string) {
  return mutate(() =>
    api.POST("/instructor/courses/{course}/announcements", {
      params: { path: { course: courseUuid } },
      body: { title, body },
    }),
  );
}

/* ---------------------------------------------------------- Notifications */

export const notificationsKey = ["notifications"] as const;

export function useNotifications(enabled: boolean) {
  return useQuery({
    queryKey: notificationsKey,
    queryFn: async () => (await unwrap(api.GET("/me/notifications"))).data,
    enabled,
    refetchInterval: 60_000,
    refetchIntervalInBackground: false,
  });
}

export function useNotificationActions() {
  const queryClient = useQueryClient();
  const refresh = () => queryClient.invalidateQueries({ queryKey: notificationsKey });
  const read = useMutation({
    mutationFn: (id: string) => mutate(() => api.POST("/me/notifications/{id}/read", { params: { path: { id } } })),
    onSuccess: refresh,
  });
  const readAll = useMutation({
    mutationFn: () => mutate(() => api.POST("/me/notifications/read-all")),
    onSuccess: refresh,
  });
  return { read, readAll };
}

export type NotificationPreferences = Awaited<ReturnType<typeof fetchPreferences>>;

async function fetchPreferences() {
  return (await unwrap(api.GET("/me/notification-preferences"))).data;
}

export function useNotificationPreferences() {
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ["notification-preferences"], queryFn: fetchPreferences });
  const update = useMutation({
    mutationFn: (patch: Partial<NotificationPreferences>) =>
      mutate(() => api.PATCH("/me/notification-preferences", { body: patch })),
    onSuccess: (res) => queryClient.setQueryData(["notification-preferences"], res.data),
  });
  return { query, update };
}
