import Link from "next/link";
import { connection } from "next/server";
import { getCategories } from "@/lib/catalog/queries";

/** Top-level categories under the header. Non-critical: renders nothing if the API is unavailable. */
export async function CategoryNav() {
  await connection(); // fetch at request time, never during the build
  const categories = await getCategories().catch(() => []);
  if (categories.length === 0) return null;

  return (
    <ul className="mx-auto flex max-w-[1340px] items-center justify-start gap-5 overflow-x-auto px-6 text-[0.8rem] whitespace-nowrap text-stone-700 2xl:justify-center">
      {categories.map((c) => (
        <li key={c.slug}>
          <Link
            href={`/courses/${c.slug}`}
            className="block py-2.5 hover:text-navy-900 hover:underline hover:decoration-gold-500 hover:decoration-2 hover:underline-offset-[10px]"
          >
            {c.name}
          </Link>
        </li>
      ))}
    </ul>
  );
}
