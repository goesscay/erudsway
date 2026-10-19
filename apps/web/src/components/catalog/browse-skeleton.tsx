import { Skeleton } from "@/components/ui/skeleton";

export function BrowseSkeleton() {
  return (
    <div className="mx-auto max-w-[1340px] px-6 py-10" aria-busy>
      <Skeleton className="h-10 w-72" />
      <Skeleton className="mt-4 h-4 w-96 max-w-full" />
      <div className="mt-12 grid gap-10 lg:grid-cols-[260px_1fr]">
        <div className="hidden space-y-3 lg:block">
          {Array.from({ length: 8 }, (_, i) => (
            <Skeleton key={i} className="h-5 w-full" />
          ))}
        </div>
        <div className="space-y-6">
          {Array.from({ length: 4 }, (_, i) => (
            <div key={i} className="grid grid-cols-[120px_1fr] gap-4 sm:grid-cols-[240px_1fr]">
              <Skeleton className="aspect-[16/9] w-full" />
              <div className="space-y-2">
                <Skeleton className="h-5 w-4/5" />
                <Skeleton className="h-4 w-3/5" />
                <Skeleton className="h-3 w-2/5" />
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
