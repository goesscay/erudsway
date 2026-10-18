import type { Photo } from "@/lib/demo-data";
import { cn } from "@/lib/utils";

export function PhotoCredit({ photo, className }: { photo: Photo; className?: string }) {
  return (
    <p className={cn("text-[0.65rem] text-stone-500", className)}>
      Photo:{" "}
      <a href={`${photo.creditUrl}?utm_source=erudsway&utm_medium=referral`} className="underline-offset-2 hover:underline">
        {photo.credit}
      </a>{" "}
      on{" "}
      <a href="https://unsplash.com/?utm_source=erudsway&utm_medium=referral" className="underline-offset-2 hover:underline">
        Unsplash
      </a>
    </p>
  );
}
