"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, mutate, unwrap } from "@/lib/api/client";

export const simulatorKey = ["me", "simulator"] as const;

/** The signed-in learner's simulator trial or paid access. Re-checks while the login is being created. */
export function useSimulatorState(enabled: boolean) {
  return useQuery({
    queryKey: simulatorKey,
    queryFn: async () => (await unwrap(api.GET("/me/simulator"))).data,
    enabled,
    refetchInterval: (query) => (query.state.data?.state === "preparing" ? 3000 : false),
  });
}
export type SimulatorState = NonNullable<ReturnType<typeof useSimulatorState>["data"]>;

export function useStartTrial() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async () => (await mutate(() => api.POST("/me/simulator/trial"))).data,
    onSuccess: (state) => queryClient.setQueryData(simulatorKey, state),
  });
}

/** A one-time sign-in link for the learner's live simulator login. */
export async function simulatorSignInLink(): Promise<string> {
  return (await mutate(() => api.POST("/me/simulator/open"))).data.url;
}
