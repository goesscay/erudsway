"use client";

import { useCallback, useSyncExternalStore } from "react";

const EVENT = "erudsway:local-pref";

function subscribe(onChange: () => void) {
  window.addEventListener("storage", onChange);
  window.addEventListener(EVENT, onChange);
  return () => {
    window.removeEventListener("storage", onChange);
    window.removeEventListener(EVENT, onChange);
  };
}

/**
 * A small per-browser preference (e.g. a collapsed sidebar), kept in localStorage. The server
 * render and hydration use the fallback, so there's no mismatch; storage that's blocked or
 * empty simply means the fallback.
 */
export function useLocalPref(key: string, fallback: string): [string, (value: string) => void] {
  const value = useSyncExternalStore(
    subscribe,
    () => {
      try {
        return window.localStorage.getItem(key) ?? fallback;
      } catch {
        return fallback;
      }
    },
    () => fallback,
  );
  const set = useCallback(
    (next: string) => {
      try {
        window.localStorage.setItem(key, next);
      } catch {
        // Private mode or blocked storage: the change just won't be remembered.
      }
      window.dispatchEvent(new Event(EVENT));
    },
    [key],
  );

  return [value, set];
}
