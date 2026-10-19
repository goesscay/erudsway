import { Logo } from "@/components/brand/logo";

export default function AuthLayout({ children }: LayoutProps<"/">) {
  return (
    <div className="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
      {/* Brand panel */}
      <aside className="dark relative hidden overflow-hidden bg-navy-950 p-12 text-foreground lg:flex lg:flex-col">
        <svg aria-hidden viewBox="0 0 600 900" className="absolute inset-y-0 right-0 h-full text-gold-400/25">
          <path
            d="M520 -20C330 60 260 200 340 330s-20 260-170 300S-10 790 60 940"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
          />
          <path
            d="M600 40C430 120 360 240 430 370s-30 250-180 290S90 820 150 960"
            fill="none"
            stroke="currentColor"
            strokeWidth="0.75"
          />
        </svg>
        <Logo inverted />
        <div className="relative mt-auto max-w-md">
          <p className="font-serif text-[2.1rem] leading-[1.15] font-medium text-white">
            Knowledge that moves minds.
          </p>
          <p className="mt-6 text-sm text-white/60">
            Learn GST, income tax, audit and company law from chartered accountants, company secretaries and advocates who practise what they teach.
          </p>
        </div>
        <p className="relative mt-16 text-xs text-white/40">© 2026 Erudsway Educorp LLP</p>
      </aside>

      {/* Form */}
      <main className="flex flex-col bg-paper">
        <div className="px-6 pt-6 lg:hidden">
          <Logo />
        </div>
        <div className="flex flex-1 items-center justify-center px-6 py-12">
          <div className="w-full max-w-[420px]">{children}</div>
        </div>
      </main>
    </div>
  );
}
