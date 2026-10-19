"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { components } from "@erudsway/api-client";
import { api, mutate, unwrap } from "@/lib/api/client";

export type Cart = components["schemas"]["CartResource"];
export type CartLine = components["schemas"]["CartLineResource"];
export type Order = components["schemas"]["OrderResource"];
export type OrderItem = Order["items"][number];

export const cartKey = ["cart"] as const;
export const ordersKey = ["orders"] as const;
export const wishlistKey = ["wishlist"] as const;

const TOKEN_KEY = "erudsway.cart-token";

/** Guests keep their cart by a token the API issues; signed-in carts live on the account. */
export function readCartToken(): string | null {
  try {
    return window.localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

function writeCartToken(token: string | null) {
  try {
    if (token) window.localStorage.setItem(TOKEN_KEY, token);
    else window.localStorage.removeItem(TOKEN_KEY);
  } catch {
    // Storage blocked: the guest cart lasts for this page only.
  }
}

function cartHeaders(): Record<string, string> {
  const token = readCartToken();
  return token ? { "X-Cart-Token": token } : {};
}

/** Every cart response is the whole priced cart; remember a new guest token if one came back. */
function keep(cart: Cart): Cart {
  if (cart.token) writeCartToken(cart.token);
  return cart;
}

export function useCart() {
  return useQuery({
    queryKey: cartKey,
    queryFn: async () => keep((await unwrap(api.GET("/cart", { headers: cartHeaders() }))).data),
    staleTime: 30_000,
  });
}

export function useCartActions() {
  const queryClient = useQueryClient();
  const set = (cart: Cart) => queryClient.setQueryData(cartKey, keep(cart));

  return {
    add: useMutation({
      mutationFn: async (slug: string) => (await mutate(() => api.POST("/cart/items", { headers: cartHeaders(), body: { course: slug } }))).data,
      onSuccess: set,
    }),
    remove: useMutation({
      mutationFn: async (slug: string) => (await mutate(() => api.DELETE("/cart/items/{slug}", { headers: cartHeaders(), params: { path: { slug } } }))).data,
      onSuccess: set,
    }),
    applyCoupon: useMutation({
      mutationFn: async (code: string) => (await mutate(() => api.POST("/cart/coupon", { headers: cartHeaders(), body: { code } }))).data,
      onSuccess: set,
    }),
    removeCoupon: useMutation({
      mutationFn: async () => (await mutate(() => api.DELETE("/cart/coupon", { headers: cartHeaders() }))).data,
      onSuccess: set,
    }),
  };
}

/** After sign-in or sign-up: fold the guest cart into the account's. Never throws. */
export async function mergeGuestCart(): Promise<Cart | null> {
  const token = readCartToken();
  if (!token) return null;
  try {
    const cart = (await mutate(() => api.POST("/cart/merge", { body: { token } }))).data;
    writeCartToken(null);
    return cart;
  } catch {
    return null;
  }
}

/** Signing out starts a fresh guest cart. */
export function forgetCartToken() {
  writeCartToken(null);
}

export function useOrders(enabled = true) {
  return useQuery({
    queryKey: ordersKey,
    queryFn: async () => (await unwrap(api.GET("/orders"))).data,
    enabled,
  });
}

export function useWishlist(enabled: boolean) {
  return useQuery({
    queryKey: wishlistKey,
    queryFn: async () => (await unwrap(api.GET("/wishlist"))).data,
    enabled,
  });
}

export function useWishlistToggle() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ slug, saved }: { slug: string; saved: boolean }) =>
      saved
        ? mutate(() => api.DELETE("/wishlist/{slug}", { params: { path: { slug } } }))
        : mutate(() => api.POST("/wishlist", { body: { course: slug } })),
    onSettled: () => queryClient.invalidateQueries({ queryKey: wishlistKey }),
  });
}

export function useGstStates() {
  return useQuery({
    queryKey: ["gst-states"],
    queryFn: async () => (await unwrap(api.GET("/checkout/states"))).data,
    staleTime: Infinity,
  });
}
