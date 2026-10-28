"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { Suspense, useState } from "react";
import { GoogleAnalytics } from "@/components/analytics/google-analytics";
import { ImpersonationBanner } from "@/components/admin/impersonation-banner";
import { TooltipProvider } from "@/components/ui/tooltip";

export function Providers({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: { refetchOnWindowFocus: false },
        },
      }),
  );

  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <ImpersonationBanner />
        {/* It reads the page address, which must happen inside Suspense for prerendering. */}
        <Suspense fallback={null}>
          <GoogleAnalytics />
        </Suspense>
        {children}
      </TooltipProvider>
    </QueryClientProvider>
  );
}
