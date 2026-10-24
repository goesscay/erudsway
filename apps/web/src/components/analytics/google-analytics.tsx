"use client";

import { useQuery } from "@tanstack/react-query";
import { usePathname } from "next/navigation";
import Script from "next/script";
import { useEffect } from "react";
import { API_URL } from "@/lib/api/client";

type SiteConfig = { google_analytics: { enabled: boolean; measurement_id: string | null } };

async function fetchSiteConfig(): Promise<SiteConfig> {
  const res = await fetch(`${API_URL}/api/v1/site-config`);
  if (!res.ok) throw new Error("site-config unavailable");
  return ((await res.json()) as { data: SiteConfig }).data;
}

/**
 * Loads Google Analytics 4 when an administrator has switched it on under Admin → Settings
 * (the Measurement ID and the on/off switch live there, not in code). Nothing is loaded or sent
 * while it is off, and the staff console is never tracked. Page changes inside the app are
 * reported by GA4's built-in enhanced measurement (history changes), so no manual page_view.
 */
export function GoogleAnalytics() {
  const pathname = usePathname();
  const { data } = useQuery({
    queryKey: ["site-config"],
    queryFn: fetchSiteConfig,
    staleTime: 5 * 60_000,
    retry: false,
  });

  const ga = data?.google_analytics;
  const id = ga?.enabled ? ga.measurement_id : null;
  const tracked = !!id && !pathname.startsWith("/admin");

  // GA's documented opt-out switch: also covers moving into /admin after the tag has loaded.
  useEffect(() => {
    if (id) (window as unknown as Record<string, unknown>)[`ga-disable-${id}`] = !tracked;
  }, [id, tracked]);

  if (!id || !tracked) return null;
  return (
    <>
      <Script src={`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`} strategy="afterInteractive" />
      <Script id="google-analytics" strategy="afterInteractive">
        {`window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', ${JSON.stringify(id)});`}
      </Script>
    </>
  );
}
