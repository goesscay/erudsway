import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { BadgeCheck, CircleAlert, Download } from "lucide-react";
import type { components } from "@erudsway/api-client";
import { buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { formatDuration } from "@/lib/format";
import { CertificateShare } from "./share";

type Certificate = components["schemas"]["CertificateResource"];
type Params = Promise<{ code: string }>;

const API_BASE = `${process.env.API_INTERNAL_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"}/api/v1`;

/** Never cached: a certificate stops being valid the moment access to the course ends. */
async function getCertificate(code: string): Promise<Certificate | null> {
  const res = await fetch(`${API_BASE}/certificates/${encodeURIComponent(code)}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error(`Certificate lookup failed: ${res.status}`);
  return ((await res.json()) as { data: Certificate }).data;
}

export async function generateMetadata({ params }: { params: Params }): Promise<Metadata> {
  const { code } = await params;
  const c = await getCertificate(code).catch(() => null);
  return {
    title: c ? `Certificate: ${c.course_title}` : "Certificate not found",
    description: c ? `${c.recipient_name} completed ${c.course_title} on Erudsway.` : undefined,
    // Learners share these links themselves; keep them out of search results.
    robots: { index: false, follow: false },
  };
}

export default function CertificatePage({ params }: { params: Params }) {
  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-[1180px] px-6 py-12">
          <Skeleton className="aspect-[1.414] w-full max-w-3xl" />
        </div>
      }
    >
      <CertificateView params={params} />
    </Suspense>
  );
}

async function CertificateView({ params }: { params: Params }) {
  const { code } = await params;
  const c = await getCertificate(code);
  if (!c) notFound();

  const issued = new Date(c.issued_at).toLocaleDateString("en-IN", {
    day: "numeric",
    month: "long",
    year: "numeric",
    timeZone: "Asia/Kolkata",
  });
  const siteUrl = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";

  return (
    <div className="mx-auto grid max-w-[1180px] gap-10 px-6 py-12 lg:grid-cols-[1fr_320px]">
      <div>
        {/* An HTML rendition of the PDF, so the page reads well without opening the file. */}
        <div
          className={`relative aspect-[1.414] w-full bg-[#fbf8f1] p-[3%] shadow-[0_24px_60px_-28px_rgb(0_27_64/0.45)] ${c.valid ? "" : "opacity-60 grayscale"}`}
        >
          <div className="absolute inset-[2.2%] border-[3px] border-navy-900" />
          <div className="absolute inset-[3.2%] border border-gold-500" />
          <div className="relative flex h-full flex-col items-center px-[6%] pt-[3%] text-center">
            <Image
              src="/brand/erudsway-logo.png"
              alt="Erudsway Educorp LLP"
              width={372}
              height={260}
              className="h-auto w-[13%]"
            />
            <p className="mt-[2.5%] text-[clamp(0.5rem,1.3vw,0.8rem)] font-bold tracking-[0.35em] text-gold-600">
              CERTIFICATE OF COMPLETION
            </p>
            <p className="mt-[2.5%] text-[clamp(0.55rem,1.2vw,0.85rem)] text-stone-500">This is to certify that</p>
            <p className="mt-[1%] font-serif text-[clamp(1.1rem,3.6vw,2.6rem)] leading-tight font-semibold text-navy-900">
              {c.recipient_name}
            </p>
            <div className="mt-[1.2%] h-px w-[40%] bg-gold-500" />
            <p className="mt-[2%] text-[clamp(0.55rem,1.2vw,0.85rem)] text-stone-500">
              has successfully completed the online course
            </p>
            <p className="mt-[1.2%] font-serif text-[clamp(0.8rem,2vw,1.4rem)] leading-snug text-navy-900">
              {c.course_title}
            </p>
            <p className="mt-[1.2%] text-[clamp(0.5rem,1.1vw,0.8rem)] text-stone-500">
              {formatDuration(c.duration_seconds)} · Completed {issued}
            </p>
            <div className="mt-auto mb-[5%] grid w-full grid-cols-3 items-end gap-[4%] text-left text-[clamp(0.45rem,1vw,0.72rem)] text-stone-500">
              <div className="border-t border-ink pt-[3%]">
                <span className="block font-semibold text-ink">{c.instructor_name}</span>Instructor
              </div>
              <div className="border-t border-ink pt-[3%]">
                <span className="block font-semibold text-ink">Erudsway Educorp LLP</span>Knowledge That Moves Minds
              </div>
              <div className="text-right">
                Certificate no. <span className="font-semibold text-navy-900">{c.serial}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <aside className="space-y-6">
        {c.valid ? (
          <p className="flex items-start gap-2.5 rounded-md bg-[#eef6f1] px-4 py-3 text-sm text-success">
            <BadgeCheck className="mt-0.5 size-5 shrink-0" />
            <span>
              <strong className="block">Verified certificate</strong>
              Issued by Erudsway Educorp LLP to {c.recipient_name}.
            </span>
          </p>
        ) : (
          <p className="flex items-start gap-2.5 rounded-md bg-destructive/5 px-4 py-3 text-sm text-destructive">
            <CircleAlert className="mt-0.5 size-5 shrink-0" />
            <span>
              <strong className="block">No longer valid</strong>
              This certificate was withdrawn because the holder&rsquo;s access to the course ended.
            </span>
          </p>
        )}

        <dl className="space-y-3 text-sm">
          <Detail label="Awarded to">{c.recipient_name}</Detail>
          <Detail label="Course">
            {c.course_slug ? (
              <Link href={`/course/${c.course_slug}`} className="font-semibold text-navy-800 hover:underline">
                {c.course_title}
              </Link>
            ) : (
              c.course_title
            )}
          </Detail>
          <Detail label="Instructor">{c.instructor_name}</Detail>
          <Detail label="Completed">{issued}</Detail>
          <Detail label="Course length">{formatDuration(c.duration_seconds)}</Detail>
          <Detail label="Certificate no.">{c.serial}</Detail>
          <Detail label="Verification code">
            <span className="tabular font-mono">{c.code}</span>
          </Detail>
        </dl>

        {c.valid && (
          <div className="space-y-3">
            <a href={c.pdf_url} className={buttonVariants({ className: "w-full" })}>
              <Download /> Download PDF
            </a>
            <CertificateShare
              url={`${siteUrl}/certificates/${c.code}`}
              courseTitle={c.course_title}
              issuedAt={c.issued_at}
              code={c.code}
            />
          </div>
        )}
      </aside>
    </div>
  );
}

function Detail({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="grid grid-cols-[120px_1fr] gap-3">
      <dt className="text-stone-500">{label}</dt>
      <dd className="text-ink">{children}</dd>
    </div>
  );
}
