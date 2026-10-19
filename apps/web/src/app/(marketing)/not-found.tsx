import Link from "next/link";
import { buttonVariants } from "@/components/ui/button";

export default function NotFound() {
  return (
    <div className="mx-auto flex max-w-md flex-col items-center px-6 py-24 text-center">
      <p className="font-serif text-6xl font-semibold text-gold-500">404</p>
      <h1 className="text-h3 mt-4">We couldn&rsquo;t find that page</h1>
      <p className="mt-2 text-sm text-stone-700">It may have moved, or the course may no longer be available.</p>
      <div className="mt-6 flex gap-3">
        <Link href="/courses" className={buttonVariants()}>
          Browse courses
        </Link>
        <Link href="/" className={buttonVariants({ variant: "outline" })}>
          Home
        </Link>
      </div>
    </div>
  );
}
