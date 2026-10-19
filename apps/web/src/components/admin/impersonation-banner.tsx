"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { Eye } from "lucide-react";
import { stopImpersonating } from "@/lib/admin/api";
import { useMe } from "@/lib/auth/session";
import { useHydrated } from "@/lib/use-hydrated";

/**
 * Shown on every page while an admin is viewing the site as someone else, so it's never
 * mistaken for their own session. Changes are refused by the API meanwhile.
 */
export function ImpersonationBanner() {
  const hydrated = useHydrated();
  const { data: me } = useMe();
  const queryClient = useQueryClient();
  const router = useRouter();
  const [busy, setBusy] = useState(false);

  if (!hydrated || !me?.impersonator) return null;

  return (
    <div
      role="status"
      className="sticky top-0 z-[60] flex flex-wrap items-center justify-center gap-x-4 gap-y-1 bg-gold-500 px-4 py-2 text-center text-sm text-navy-950"
    >
      <span>
        <Eye className="mr-1.5 inline size-4 align-[-3px]" />
        <strong>{me.impersonator.name}</strong>, you&rsquo;re viewing the site as <strong>{me.name}</strong> ({me.email}
        ). Read-only.
      </span>
      <button
        type="button"
        disabled={busy}
        onClick={async () => {
          setBusy(true);
          try {
            await stopImpersonating();
          } finally {
            // Everything cached belonged to the other account.
            queryClient.clear();
            router.replace(`/admin/users/${me.id}`);
          }
        }}
        className="rounded-sm bg-navy-950 px-2.5 py-1 text-xs font-bold text-white hover:bg-navy-800"
      >
        {busy ? "Switching back…" : "Stop viewing as"}
      </button>
    </div>
  );
}
