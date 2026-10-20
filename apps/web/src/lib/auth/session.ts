"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import type { components } from "@erudsway/api-client";
import { api, ApiRequestError, mutate, resetCsrf, unwrap } from "@/lib/api/client";
import { cartKey, forgetCartToken, mergeGuestCart } from "@/lib/commerce/api";

export type CurrentUser = components["schemas"]["UserResource"];

export const meKey = ["me"] as const;

/** The signed-in user, or null for guests. Shared across the app through TanStack Query. */
export function useMe() {
  return useQuery({
    queryKey: meKey,
    queryFn: async (): Promise<CurrentUser | null> => {
      try {
        const res = await unwrap(api.GET("/me"));
        return res.data;
      } catch (e) {
        if (e instanceof ApiRequestError && (e.status === 401 || e.code === "ACCOUNT_SUSPENDED")) return null;
        throw e;
      }
    },
    staleTime: 60_000,
    retry: false,
  });
}

export function useSession() {
  const queryClient = useQueryClient();
  // A guest's cart follows them into their account.
  const adoptGuestCart = async () => {
    const cart = await mergeGuestCart();
    if (cart) queryClient.setQueryData(cartKey, cart);
    else void queryClient.invalidateQueries({ queryKey: cartKey });
  };

  return {
    async login(body: { email: string; password: string; remember?: boolean }) {
      const res = await mutate(() => api.POST("/auth/login", { body }));
      resetCsrf(); // session id rotated
      queryClient.setQueryData(meKey, res.data);
      await adoptGuestCart();
      return res.data;
    },
    async register(body: {
      name: string;
      email: string;
      password: string;
      password_confirmation: string;
      accept_terms: boolean;
    }) {
      // Sent only when ticked; the API refuses sign-up without it (18+ and Terms accepted).
      const { accept_terms, ...rest } = body;
      const res = await mutate(() =>
        api.POST("/auth/register", { body: { ...rest, ...(accept_terms && { accept_terms: true as const }) } }),
      );
      resetCsrf();
      queryClient.setQueryData(meKey, res.data);
      await adoptGuestCart();
      return res.data;
    },
    async logout() {
      try {
        await mutate(() => api.POST("/auth/logout"));
      } finally {
        resetCsrf();
        forgetCartToken();
        queryClient.setQueryData(meKey, null);
        queryClient.removeQueries({ predicate: (q) => q.queryKey[0] !== meKey[0] });
      }
    },
    setUser(user: CurrentUser) {
      queryClient.setQueryData(meKey, user);
    },
  };
}

export function hasPermission(user: CurrentUser | null | undefined, permission: string): boolean {
  return !!user?.permissions.includes(permission);
}

export function isStaff(user: CurrentUser | null | undefined): boolean {
  return !!user && user.permissions.length > 0;
}

/** Only allow same-site relative redirects after login (prevents open redirects). */
export function safeNext(next: string | null | undefined, fallback = "/my-learning"): string {
  // Browsers treat "\" like "/", so "/\evil.com" points at another site, and control
  // characters can smuggle the same. Only a plain path on this site gets through.
  if (!next || !next.startsWith("/") || next.startsWith("//") || /[\\\x00-\x1f]/.test(next)) return fallback;
  return next;
}
