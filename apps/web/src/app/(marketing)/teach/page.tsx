import type { Metadata } from "next";
import Image from "next/image";
import { PhotoCredit } from "@/components/brand/photo-credit";
import { photos } from "@/lib/demo-data";
import { ApplySection } from "./apply-section";

export const metadata: Metadata = {
  title: "Teach on Erudsway",
  description: "Share what you know with learners across India. Apply to become an Erudsway instructor.",
};

const promises = [
  ["Reviewed, not crowded", "Every instructor and course is approved by our team, so your work isn't lost in a sea of low-effort content."],
  ["Paid every month", "A clear statement of each sale, the commission applied and your payout — no guesswork."],
  ["We handle the rest", "Payments, GST invoices, video hosting and learner support are on us."],
];

export default function TeachPage() {
  return (
    <>
      <section className="dark bg-navy-950 text-foreground">
        <div className="mx-auto grid max-w-[1340px] items-center gap-12 px-6 py-16 lg:grid-cols-[1.1fr_1fr] lg:py-20">
          <div>
            <p className="mb-4 text-xs font-bold tracking-[0.2em] text-gold-400 uppercase">Teach on Erudsway</p>
            <h1 className="max-w-[16ch] text-[2.6rem] leading-[1.05] tracking-[-0.02em] sm:text-display">
              Your expertise, a classroom of thousands.
            </h1>
            <p className="mt-6 max-w-[52ch] text-lg leading-relaxed text-white/75">
              We work with practising professionals — engineers, chartered accountants, designers, analysts — who want to
              teach properly. Apply below; we review every application personally.
            </p>
          </div>
          <div>
            <div className="relative aspect-[4/3] overflow-hidden rounded-md">
              <Image src={photos.teach.src} alt={photos.teach.alt} fill priority sizes="(min-width: 1024px) 560px, 100vw" className="object-cover" />
            </div>
            <PhotoCredit photo={photos.teach} className="mt-2 text-right text-white/40" />
          </div>
        </div>
      </section>

      <section className="border-b border-line bg-white">
        <ul className="mx-auto grid max-w-[1340px] gap-8 px-6 py-12 md:grid-cols-3">
          {promises.map(([title, body], i) => (
            <li key={title} className="flex gap-4">
              <span className="tabular font-serif text-2xl text-gold-600">0{i + 1}</span>
              <span>
                <span className="block font-semibold text-ink">{title}</span>
                <span className="mt-1 block text-sm leading-relaxed text-stone-700">{body}</span>
              </span>
            </li>
          ))}
        </ul>
      </section>

      <section id="apply" className="mx-auto max-w-3xl scroll-mt-28 px-6 py-16">
        <ApplySection />
      </section>
    </>
  );
}
