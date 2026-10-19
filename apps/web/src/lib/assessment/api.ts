"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { components } from "@erudsway/api-client";
import { api, mutate, unwrap } from "@/lib/api/client";
import { postForm } from "@/lib/authoring/editor";
import { learnKey, myLearningKey } from "@/lib/learning/api";

export type Submission = components["schemas"]["SubmissionResource"];
export type Certificate = components["schemas"]["CertificateResource"];

async function fetchQuiz(lecture: number) {
  return (await unwrap(api.GET("/learn/lectures/{lecture}/quiz", { params: { path: { lecture } } }))).data;
}
export type LearnerQuiz = Awaited<ReturnType<typeof fetchQuiz>>;
export type QuizResult = NonNullable<LearnerQuiz["last_result"]>;

export function useQuiz(lectureId: number) {
  return useQuery({ queryKey: ["quiz", lectureId], queryFn: () => fetchQuiz(lectureId) });
}

/** Grade an attempt; on a pass the course outline (progress, certificate) is refreshed. */
export function useSubmitQuiz(lectureId: number, slug: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (answers: Record<number, number[]>) =>
      mutate(() =>
        api.POST("/learn/lectures/{lecture}/quiz/attempts", {
          params: { path: { lecture: lectureId } },
          body: {
            answers: Object.entries(answers).map(([question_id, option_ids]) => ({
              question_id: Number(question_id),
              option_ids,
            })),
          },
        }),
      ),
    onSuccess: async (res) => {
      await queryClient.invalidateQueries({ queryKey: ["quiz", lectureId] });
      if (res.data.passed && res.data.recorded) {
        await Promise.all([
          queryClient.invalidateQueries({ queryKey: learnKey(slug) }),
          queryClient.invalidateQueries({ queryKey: myLearningKey }),
        ]);
      }
    },
  });
}

async function fetchAssignment(lecture: number) {
  return (await unwrap(api.GET("/learn/lectures/{lecture}/assignment", { params: { path: { lecture } } }))).data;
}
export type LearnerAssignment = Awaited<ReturnType<typeof fetchAssignment>>;

export function useAssignment(lectureId: number) {
  return useQuery({ queryKey: ["assignment", lectureId], queryFn: () => fetchAssignment(lectureId) });
}

export function useSubmitAssignment(lectureId: number, slug: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ body, file }: { body: string; file: File | null }) => {
      const form = new FormData();
      if (body.trim()) form.append("body", body.trim());
      if (file) form.append("file", file);
      return postForm<{ data: Submission }>(`/learn/lectures/${lectureId}/assignment`, form);
    },
    onSuccess: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: ["assignment", lectureId] }),
        queryClient.invalidateQueries({ queryKey: learnKey(slug) }),
        queryClient.invalidateQueries({ queryKey: myLearningKey }),
      ]),
  });
}

export function useSubmissionInbox(status: "submitted" | "reviewed" | "all", page: number) {
  return useQuery({
    queryKey: ["instructor", "submissions", status, page],
    queryFn: async () => unwrap(api.GET("/instructor/submissions", { params: { query: { status, page } } })),
    placeholderData: keepPreviousData,
  });
}

export function reviewSubmission(id: number, feedback: string) {
  return mutate(() =>
    api.PUT("/instructor/submissions/{submission}/review", {
      params: { path: { submission: id } },
      body: { feedback },
    }),
  );
}

export function useMyCertificates() {
  return useQuery({
    queryKey: ["my-certificates"],
    queryFn: async () => (await unwrap(api.GET("/me/certificates"))).data,
  });
}
