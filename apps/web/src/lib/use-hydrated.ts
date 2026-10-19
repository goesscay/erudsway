"use client";

import { useSyncExternalStore } from "react";

const noop = () => () => {};

/**
 * False on the server and during hydration, true afterwards. Use it before rendering
 * anything that depends on client-only state (like the signed-in user), so the first
 * client render matches the server HTML even when that state is already in the cache.
 */
export function useHydrated(): boolean {
  return useSyncExternalStore(
    noop,
    () => true,
    () => false,
  );
}
