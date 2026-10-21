"use client";

import { useDeferredValue, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2 } from "lucide-react";
import type { paths } from "@erudsway/api-client";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { useGstStates } from "@/lib/commerce/api";
import { formatPaise } from "@/lib/format";

type Learner = { id: string; name: string; email: string };
type Course = { slug: string; title: string };
type StateCode = paths["/admin/offline-payments"]["post"]["requestBody"]["content"]["application/json"]["state_code"];
type Method = "cash" | "bank_transfer" | "upi" | "cheque" | "card" | "other";

const METHODS: { value: Method; label: string; reference: string }[] = [
  { value: "upi", label: "UPI", reference: "UPI reference / UTR" },
  { value: "bank_transfer", label: "Bank transfer", reference: "UTR / transaction ID" },
  { value: "cash", label: "Cash", reference: "Receipt number" },
  { value: "cheque", label: "Cheque", reference: "Cheque number" },
  { value: "card", label: "Card (in person)", reference: "Terminal reference" },
  { value: "other", label: "Other", reference: "Reference" },
];

const selectClass = "h-10 w-full rounded-md border border-input bg-white px-3 text-sm";

function today() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

/**
 * Record a course a learner paid for outside the website. They get the course, a GST invoice
 * and a receipt, as if they'd paid online. `learner` pre-fills who paid (from their user page).
 */
export function OfflinePaymentSheet({
  open,
  learner,
  onClose,
}: {
  open: boolean;
  learner?: Learner;
  onClose: () => void;
}) {
  return (
    <Sheet open={open} onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="overflow-y-auto data-[side=right]:w-full data-[side=right]:sm:max-w-lg">
        <SheetHeader className="border-b border-line">
          <SheetTitle>Record offline payment</SheetTitle>
          <SheetDescription>
            For money received outside the website. The learner gets the course, a GST invoice and a receipt email.
          </SheetDescription>
        </SheetHeader>
        {open && <OfflinePaymentForm key={learner?.id ?? "any"} preset={learner} onClose={onClose} />}
      </SheetContent>
    </Sheet>
  );
}

function OfflinePaymentForm({ preset, onClose }: { preset?: Learner; onClose: () => void }) {
  const queryClient = useQueryClient();
  const states = useGstStates();
  const [learner, setLearner] = useState<Learner | null>(preset ?? null);
  const [course, setCourse] = useState<Course | null>(null);
  const [editionId, setEditionId] = useState<number | null>(null);
  const [amount, setAmount] = useState("");
  const [method, setMethod] = useState<Method>("upi");
  const [reference, setReference] = useState("");
  const [receivedOn, setReceivedOn] = useState(today());
  const [note, setNote] = useState("");
  const [name, setName] = useState(preset?.name ?? "");
  const [stateCode, setStateCode] = useState("");
  const [business, setBusiness] = useState(false);
  const [gstin, setGstin] = useState("");
  const [businessName, setBusinessName] = useState("");
  const [address, setAddress] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState<{ number: string; total: number } | null>(null);

  const detail = useQuery({
    queryKey: ["course", course?.slug],
    queryFn: async () => (await unwrap(api.GET("/courses/{slug}", { params: { path: { slug: course!.slug } } }))).data,
    enabled: !!course,
  });
  const editions = detail.data?.editions ?? [];
  const chosenEdition = editions.length
    ? (editionId ?? editions.find((e) => e.is_recommended)?.id ?? editions[0].id)
    : null;

  const quote = useQuery({
    queryKey: ["admin", "offline-quote", learner?.id, course?.slug, chosenEdition],
    queryFn: async () =>
      (
        await unwrap(
          api.GET("/admin/offline-payments/quote", {
            params: {
              query: {
                user: learner!.id,
                course: course!.slug,
                ...(chosenEdition !== null ? { edition_id: chosenEdition } : {}),
              },
            },
          }),
        )
      ).data,
    enabled: !!learner && !!course && detail.isSuccess,
  });
  const listPrice = Number(quote.data?.list_price ?? 0);
  const amountPaise = Math.round(Number(amount || (listPrice / 100).toString()) * 100);
  const ref = METHODS.find((m) => m.value === method)!;

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!learner || !course || !quote.data) return;
    setBusy(true);
    setError(null);
    setFieldErrors({});
    try {
      const res = await mutate(() =>
        api.POST("/admin/offline-payments", {
          body: {
            user: learner.id,
            course: course.slug,
            edition_id: chosenEdition,
            amount: amountPaise,
            method,
            reference: reference.trim() || null,
            received_on: receivedOn,
            note: note.trim() || null,
            name: name.trim(),
            state_code: stateCode as StateCode,
            gstin: business ? gstin.trim() || null : null,
            business_name: business ? businessName.trim() || null : null,
            address: business ? address.trim() || null : null,
          },
        }),
      );
      setDone({ number: res.data.number, total: res.data.total });
      await queryClient.invalidateQueries({ queryKey: ["admin", "orders"] });
      await queryClient.invalidateQueries({ queryKey: ["admin", "user", learner.id] });
      await queryClient.invalidateQueries({ queryKey: ["admin", "user-enrollments", learner.id] });
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setFieldErrors(Object.fromEntries(Object.entries(err.fields).map(([k, v]) => [k, v[0] ?? ""])));
        setError(Object.keys(err.fields).length ? "Check the highlighted details." : err.message);
      } else {
        setError("Something went wrong.");
      }
    } finally {
      setBusy(false);
    }
  };

  if (done && learner && course) {
    return (
      <div className="space-y-4 px-4 py-6">
        <FormAlert tone="success">
          <span className="flex items-start gap-2">
            <CheckCircle2 className="mt-0.5 size-4 shrink-0" aria-hidden />
            <span>
              Recorded order <strong className="font-mono">{done.number}</strong> for {formatPaise(done.total, true)}.{" "}
              {learner.name} now has {quote.data?.title ?? course.title}. The invoice and receipt go to {learner.email}.
            </span>
          </span>
        </FormAlert>
        <div className="flex gap-2">
          <Button onClick={onClose}>Done</Button>
          {!preset && (
            <Button
              variant="outline"
              onClick={() => {
                setDone(null);
                setLearner(null);
                setCourse(null);
                setEditionId(null);
                setAmount("");
                setReference("");
                setNote("");
                setName("");
              }}
            >
              Record another
            </Button>
          )}
        </div>
      </div>
    );
  }

  return (
    <form onSubmit={submit} className="space-y-6 px-4 pb-8" noValidate>
      {error && <FormAlert>{error}</FormAlert>}

      <fieldset className="min-w-0 space-y-3">
        <legend className="mb-2 font-sans text-sm font-bold text-ink">1. Learner and course</legend>
        {learner ? (
          <Picked
            label="Learner"
            title={learner.name}
            subtitle={learner.email}
            onChange={preset ? undefined : () => setLearner(null)}
          />
        ) : (
          <LearnerSearch
            onPick={(l) => {
              setLearner(l);
              setName(l.name);
            }}
          />
        )}
        {course ? (
          <Picked
            label="Course"
            title={course.title}
            onChange={() => {
              setCourse(null);
              setEditionId(null);
              setAmount("");
            }}
          />
        ) : (
          <CourseSearch onPick={setCourse} />
        )}
        {editions.length > 0 && (
          <div className="space-y-1.5">
            <Label htmlFor="offline-edition">Edition</Label>
            <select
              id="offline-edition"
              value={chosenEdition ?? ""}
              onChange={(e) => {
                setEditionId(Number(e.target.value));
                setAmount("");
              }}
              className={selectClass}
            >
              {editions.map((ed) => (
                <option key={ed.id} value={ed.id}>
                  {ed.name} — {formatPaise(ed.price.amount)}
                  {ed.access_days ? ` · ${ed.access_days} days` : ""}
                </option>
              ))}
            </select>
          </div>
        )}
        {quote.data?.problem && <FormAlert>{quote.data.problem}</FormAlert>}
        {quote.data && !quote.data.problem && quote.data.upgrade_from && (
          <p className="text-xs text-stone-600">
            Upgrade from {quote.data.upgrade_from}: they pay the difference, {formatPaise(listPrice, true)}.
          </p>
        )}
      </fieldset>

      {quote.data && !quote.data.problem && (
        <>
          <fieldset className="min-w-0 space-y-3 border-t border-line pt-5">
            <legend className="mb-2 font-sans text-sm font-bold text-ink">2. Payment received</legend>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label htmlFor="offline-amount">Amount (₹, GST included)</Label>
                <Input
                  id="offline-amount"
                  inputMode="decimal"
                  value={amount}
                  placeholder={(listPrice / 100).toFixed(2)}
                  onChange={(e) => setAmount(e.target.value.replace(/[^\d.]/g, ""))}
                  aria-invalid={!!fieldErrors.amount}
                  aria-describedby="offline-amount-hint"
                />
                <p id="offline-amount-hint" className="text-xs text-stone-500">
                  Price {formatPaise(listPrice, true)}
                  {amountPaise < listPrice && amountPaise > 0 && (
                    <> · discount {formatPaise(listPrice - amountPaise, true)}</>
                  )}
                </p>
                {fieldErrors.amount && <p className="text-xs text-destructive">{fieldErrors.amount}</p>}
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="offline-date">Date received</Label>
                <Input
                  id="offline-date"
                  type="date"
                  max={today()}
                  value={receivedOn}
                  onChange={(e) => setReceivedOn(e.target.value)}
                  aria-invalid={!!fieldErrors.received_on}
                />
                {fieldErrors.received_on && <p className="text-xs text-destructive">{fieldErrors.received_on}</p>}
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="offline-method">Paid by</Label>
                <select
                  id="offline-method"
                  value={method}
                  onChange={(e) => setMethod(e.target.value as Method)}
                  className={selectClass}
                >
                  {METHODS.map((m) => (
                    <option key={m.value} value={m.value}>
                      {m.label}
                    </option>
                  ))}
                </select>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="offline-reference">
                  {ref.reference} <span className="font-normal text-stone-500">(optional)</span>
                </Label>
                <Input
                  id="offline-reference"
                  value={reference}
                  maxLength={100}
                  onChange={(e) => setReference(e.target.value)}
                />
              </div>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="offline-note">
                Note <span className="font-normal text-stone-500">(optional, staff only)</span>
              </Label>
              <Textarea
                id="offline-note"
                rows={2}
                maxLength={500}
                value={note}
                onChange={(e) => setNote(e.target.value)}
                placeholder="e.g. Paid at the Chennai workshop"
              />
            </div>
          </fieldset>

          <fieldset className="min-w-0 space-y-3 border-t border-line pt-5">
            <legend className="mb-1 font-sans text-sm font-bold text-ink">3. Invoice details</legend>
            <p className="text-xs text-stone-500">Printed on the GST invoice. The state decides CGST + SGST or IGST.</p>
            <div className="space-y-1.5">
              <Label htmlFor="offline-name">Name on invoice</Label>
              <Input
                id="offline-name"
                value={name}
                maxLength={120}
                onChange={(e) => setName(e.target.value)}
                aria-invalid={!!fieldErrors.name}
              />
              {fieldErrors.name && <p className="text-xs text-destructive">{fieldErrors.name}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="offline-state">State</Label>
              <select
                id="offline-state"
                value={stateCode}
                onChange={(e) => setStateCode(e.target.value)}
                className={selectClass}
                aria-invalid={!!fieldErrors.state_code}
              >
                <option value="">Choose the learner&rsquo;s state…</option>
                {(states.data ?? []).map((s) => (
                  <option key={s.code} value={s.code}>
                    {s.name}
                  </option>
                ))}
              </select>
              {fieldErrors.state_code && <p className="text-xs text-destructive">{fieldErrors.state_code}</p>}
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={business}
                onChange={(e) => setBusiness(e.target.checked)}
                className="size-4 accent-[var(--navy-900)]"
              />
              Invoice a business (GSTIN)
            </label>
            {business && (
              <div className="space-y-3">
                <div className="space-y-1.5">
                  <Label htmlFor="offline-gstin">GSTIN</Label>
                  <Input
                    id="offline-gstin"
                    value={gstin}
                    maxLength={15}
                    onChange={(e) => setGstin(e.target.value.toUpperCase())}
                    aria-invalid={!!fieldErrors.gstin}
                  />
                  {fieldErrors.gstin && <p className="text-xs text-destructive">{fieldErrors.gstin}</p>}
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="offline-business">Registered business name</Label>
                  <Input
                    id="offline-business"
                    value={businessName}
                    maxLength={160}
                    onChange={(e) => setBusinessName(e.target.value)}
                    aria-invalid={!!fieldErrors.business_name}
                  />
                  {fieldErrors.business_name && <p className="text-xs text-destructive">{fieldErrors.business_name}</p>}
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="offline-address">Business address</Label>
                  <Textarea
                    id="offline-address"
                    rows={2}
                    maxLength={300}
                    value={address}
                    onChange={(e) => setAddress(e.target.value)}
                    aria-invalid={!!fieldErrors.address}
                  />
                  {fieldErrors.address && <p className="text-xs text-destructive">{fieldErrors.address}</p>}
                </div>
              </div>
            )}
          </fieldset>

          <div className="flex flex-wrap items-center gap-3 border-t border-line pt-5">
            <Button
              type="submit"
              disabled={
                busy || !stateCode || name.trim().length < 2 || !(amountPaise >= 100 && amountPaise <= listPrice)
              }
            >
              {busy
                ? "Recording…"
                : `Record ${formatPaise(amountPaise > 0 ? amountPaise : listPrice, true)} and give access`}
            </Button>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
          </div>
        </>
      )}
    </form>
  );
}

function Picked({
  label,
  title,
  subtitle,
  onChange,
}: {
  label: string;
  title: string;
  subtitle?: string;
  onChange?: () => void;
}) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-md border border-line bg-white px-3 py-2 text-sm">
      <span className="min-w-0">
        <span className="block text-xs text-stone-500">{label}</span>
        <span className="block truncate font-semibold text-ink">{title}</span>
        {subtitle && <span className="block truncate text-xs text-stone-500">{subtitle}</span>}
      </span>
      {onChange && (
        <Button type="button" size="sm" variant="ghost" onClick={onChange}>
          Change<span className="sr-only"> {label.toLowerCase()}</span>
        </Button>
      )}
    </div>
  );
}

function LearnerSearch({ onPick }: { onPick: (l: Learner) => void }) {
  const [search, setSearch] = useState("");
  const q = useDeferredValue(search.trim());
  const results = useQuery({
    queryKey: ["admin", "offline-learners", q],
    queryFn: async () =>
      (await unwrap(api.GET("/admin/offline-payments/learners", { params: { query: { q } } }))).data.items,
    enabled: q.length >= 2,
  });

  return (
    <div className="space-y-1.5">
      <Label htmlFor="offline-learner">Learner</Label>
      <Input
        id="offline-learner"
        placeholder="Search by name or email…"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        autoComplete="off"
      />
      {q.length >= 2 && (
        <ul className="max-h-56 overflow-y-auto rounded-md border border-line bg-white shadow-sm">
          {results.isPending ? (
            <li className="px-3 py-2 text-sm text-stone-500">Searching…</li>
          ) : (results.data ?? []).length === 0 ? (
            <li className="px-3 py-2 text-sm text-stone-500">
              No account matches. Add them from Users first, or ask them to sign up.
            </li>
          ) : (
            results.data!.map((u) => (
              <li key={u.id}>
                <button
                  type="button"
                  disabled={u.suspended}
                  onClick={() => onPick(u)}
                  className="block w-full px-3 py-2 text-left text-sm hover:bg-paper disabled:opacity-50"
                >
                  <span className="font-semibold text-ink">{u.name}</span>
                  <span className="block text-xs text-stone-500">
                    {u.email}
                    {u.suspended && " · suspended"}
                  </span>
                </button>
              </li>
            ))
          )}
        </ul>
      )}
    </div>
  );
}

function CourseSearch({ onPick }: { onPick: (c: Course) => void }) {
  const [search, setSearch] = useState("");
  const q = useDeferredValue(search.trim());
  const results = useQuery({
    queryKey: ["admin", "course-search", q],
    queryFn: async () => (await unwrap(api.GET("/courses", { params: { query: { q, per_page: 6 } } }))).data,
    enabled: q.length >= 2,
  });

  return (
    <div className="space-y-1.5">
      <Label htmlFor="offline-course">Course</Label>
      <Input
        id="offline-course"
        placeholder="Search published courses…"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        autoComplete="off"
      />
      {q.length >= 2 && (
        <ul className="max-h-56 overflow-y-auto rounded-md border border-line bg-white shadow-sm">
          {results.isPending ? (
            <li className="px-3 py-2 text-sm text-stone-500">Searching…</li>
          ) : (results.data ?? []).length === 0 ? (
            <li className="px-3 py-2 text-sm text-stone-500">No published course matches.</li>
          ) : (
            results.data!.map((c) => (
              <li key={c.id}>
                <button
                  type="button"
                  onClick={() => onPick({ slug: c.slug, title: c.title })}
                  className="block w-full px-3 py-2 text-left text-sm font-semibold text-ink hover:bg-paper"
                >
                  {c.title}
                </button>
              </li>
            ))
          )}
        </ul>
      )}
    </div>
  );
}
