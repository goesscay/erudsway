export function AdminPageHeader({ title, description, children }: { title: string; description?: string; children?: React.ReactNode }) {
  return (
    <header className="flex flex-wrap items-end justify-between gap-4 border-b border-line bg-white px-8 py-6">
      <div>
        <h1 className="text-h2">{title}</h1>
        {description && <p className="mt-1 text-sm text-stone-500">{description}</p>}
      </div>
      {children}
    </header>
  );
}

export function StatusPill({ status }: { status: string }) {
  const styles: Record<string, string> = {
    pending: "bg-gold-50 text-gold-600 ring-gold-200",
    approved: "bg-[#e7f1ec] text-success ring-[#cfe3d8]",
    active: "bg-[#e7f1ec] text-success ring-[#cfe3d8]",
    rejected: "bg-destructive/5 text-destructive ring-destructive/20",
    suspended: "bg-destructive/5 text-destructive ring-destructive/20",
  };
  return (
    <span className={`inline-flex h-6 items-center rounded-sm px-2 text-[0.7rem] font-bold tracking-wide capitalize ring-1 ring-inset ${styles[status] ?? "bg-paper-2 text-stone-700 ring-line"}`}>
      {status}
    </span>
  );
}

export function formatDate(iso: string | null | undefined) {
  if (!iso) return "—";
  return new Date(iso).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" });
}
