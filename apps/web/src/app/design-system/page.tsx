import type { Metadata } from "next";
import { Logo } from "@/components/brand/logo";
import { SectionHeading } from "@/components/brand/section-heading";
import { CourseBadge } from "@/components/course/course-badge";
import { CourseCard } from "@/components/course/course-card";
import { InstructorAvatar } from "@/components/course/instructor-avatar";
import { Rating } from "@/components/course/rating";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Textarea } from "@/components/ui/textarea";
import { sampleCards } from "@/lib/demo-data";

export const metadata: Metadata = { title: "Design system", robots: { index: false } };

const swatches = [
  ["navy-950", "#000F26", "Footer, player chrome"],
  ["navy-900", "#001B40", "Primary — brand, buttons, headings"],
  ["navy-700", "#163A6E", "Hover, secondary emphasis"],
  ["navy-100", "#E6EBF2", "Selected rows, subtle fills"],
  ["gold-600", "#9A6D1F", "Gold text on light (AA)"],
  ["gold-500", "#B8862F", "Accents, ratings, focus ring"],
  ["gold-400", "#D1A34E", "Gold on dark surfaces"],
  ["gold-200", "#EFDCAE", "Hairlines, selection"],
  ["paper", "#FAF8F3", "Page background"],
  ["paper-2", "#F3EFE6", "Muted panels"],
  ["line", "#E4DED1", "Borders"],
  ["ink", "#15130F", "Body text"],
];

const type = [
  ["text-display", "Display · Fraunces 56", "Learned from practice"],
  ["text-h1", "H1 · Fraunces 40", "GST Practitioner Course"],
  ["text-h2", "H2 · Fraunces 30", "Course content"],
  ["text-h3", "H3 · Fraunces 22", "What you'll learn"],
];

export default function DesignSystemPage() {
  return (
    <main className="mx-auto max-w-[1180px] space-y-20 px-6 py-14">
      <header className="flex flex-wrap items-end justify-between gap-6 border-b border-line pb-8">
        <div>
          <Logo />
          <h1 className="mt-6 text-h1">Design system</h1>
          <p className="mt-2 max-w-[60ch] text-stone-700">
            Tokens and components for Erudsway. Navy carries the brand; gold is an accent that should cover less than a
            twentieth of any screen.
          </p>
        </div>
        <p className="text-xs text-stone-500">Phase 0 · for review</p>
      </header>

      <section>
        <SectionHeading eyebrow="Colour" title="Palette" />
        <ul className="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          {swatches.map(([name, hex, use]) => (
            <li key={name} className="overflow-hidden rounded-md border border-line bg-white">
              <div className="h-20" style={{ background: `var(--${name})` }} />
              <div className="p-3">
                <p className="text-sm font-bold text-ink">{name}</p>
                <p className="tabular text-xs text-stone-500">{hex}</p>
                <p className="mt-1 text-xs text-stone-700">{use}</p>
              </div>
            </li>
          ))}
        </ul>
      </section>

      <section>
        <SectionHeading eyebrow="Typography" title="Fraunces for voice, Figtree for work">
          Serif headlines give the editorial, academic tone; the sans handles all interface text. Numbers use tabular
          figures so prices and stats align.
        </SectionHeading>
        <div className="mt-8 divide-y divide-line border-y border-line">
          {type.map(([cls, label, sample]) => (
            <div key={cls} className="grid items-baseline gap-4 py-5 md:grid-cols-[200px_1fr]">
              <p className="text-xs font-semibold text-stone-500">{label}</p>
              <p
                className={cls}
                style={{ fontFamily: "var(--font-fraunces)", fontWeight: 600, color: "var(--navy-900)" }}
              >
                {sample}
              </p>
            </div>
          ))}
          <div className="grid items-baseline gap-4 py-5 md:grid-cols-[200px_1fr]">
            <p className="text-xs font-semibold text-stone-500">Body · Figtree 16</p>
            <p className="max-w-[65ch] leading-relaxed text-stone-700">
              Learn at your own pace on any device. Every course is reviewed by our team before it is published, and
              comes with a GST invoice.
            </p>
          </div>
          <div className="grid items-baseline gap-4 py-5 md:grid-cols-[200px_1fr]">
            <p className="text-xs font-semibold text-stone-500">Eyebrow · Figtree 12 caps</p>
            <p className="text-xs font-bold tracking-[0.18em] text-gold-600 uppercase">Most enrolled</p>
          </div>
        </div>
      </section>

      <section>
        <SectionHeading eyebrow="Actions" title="Buttons" />
        <div className="mt-8 space-y-5">
          <div className="flex flex-wrap items-center gap-3">
            <Button size="lg">Add to cart</Button>
            <Button>Primary</Button>
            <Button variant="outline">Outline</Button>
            <Button variant="secondary">Secondary</Button>
            <Button variant="ghost">Ghost</Button>
            <Button variant="link">Link</Button>
            <Button variant="destructive">Delete</Button>
            <Button disabled>Disabled</Button>
          </div>
          <div className="dark flex flex-wrap items-center gap-3 rounded-md bg-navy-900 p-6 text-foreground">
            <Button size="lg">Start teaching</Button>
            <Button variant="outline">Outline on dark</Button>
            <Button variant="ghost">Ghost on dark</Button>
          </div>
        </div>
      </section>

      <section className="grid gap-12 lg:grid-cols-2">
        <div>
          <SectionHeading eyebrow="Forms" title="Inputs" />
          <form className="mt-8 space-y-5">
            <div className="space-y-2">
              <Label htmlFor="ds-email">Email</Label>
              <Input id="ds-email" type="email" placeholder="you@example.com" />
            </div>
            <div className="space-y-2">
              <Label htmlFor="ds-err">Coupon code</Label>
              <Input id="ds-err" defaultValue="GSTFEST50" aria-invalid />
              <p className="text-xs text-destructive">This coupon has expired.</p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="ds-bio">Instructor bio</Label>
              <Textarea id="ds-bio" placeholder="Tell learners about your experience" />
            </div>
            <Label className="flex items-center gap-2 font-normal">
              <Checkbox defaultChecked /> Email me about new courses
            </Label>
          </form>
        </div>
        <div>
          <SectionHeading eyebrow="Signals" title="Ratings, badges, progress" />
          <div className="mt-8 space-y-6">
            <Rating value={4.7} count={18342} size="md" />
            <Rating value={3.4} count={120} />
            <div className="flex gap-2">
              <CourseBadge badge="bestseller" />
              <CourseBadge badge="highest_rated" />
              <CourseBadge badge="new" />
            </div>
            <div className="space-y-1">
              <Progress value={62} className="h-1.5" aria-label="62% complete" />
              <p className="tabular text-xs text-stone-500">62% complete</p>
            </div>
            <div className="flex items-center gap-3">
              <InstructorAvatar name="Vikram Sethi" />
              <InstructorAvatar name="Nandini Iyer" className="size-10 text-sm" />
              <InstructorAvatar name="Farah Qureshi" className="size-8 text-xs" />
            </div>
          </div>
        </div>
      </section>

      <section>
        <SectionHeading eyebrow="Catalogue" title="Course card" />
        <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {sampleCards.map((c) => (
            <CourseCard key={c.id} course={c} />
          ))}
        </div>
      </section>
    </main>
  );
}
