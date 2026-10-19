"use client";

import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { CircleAlert } from "lucide-react";
import type { components } from "@erudsway/api-client";
import { AdminPageHeader, formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { formatPaise } from "@/lib/format";
import { cn } from "@/lib/utils";

type Payout = components["schemas"]["PayoutResource"];
type Account = components["schemas"]["PayoutAccountResource"];
type Tab = "draft" | "approved" | "paid" | "cancelled" | "accounts";

const tabs: { value: Tab; label: string }[] = [
  { value: "draft", label: "To approve" },
  { value: "approved", label: "To pay" },
  { value: "paid", label: "Paid" },
  { value: "cancelled", label: "Cancelled" },
  { value: "accounts", label: "Payout details to verify" },
];

/** Payouts normally cover earnings that became available by the end of last month. */
function lastMonthEnd(): string {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 0).toLocaleDateString("en-CA");
}

const errorText = (e: unknown) => (e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.");

export default function PayoutsPage() {
  return <RequireAuth permission="manage_finance">{() => <Payouts />}</RequireAuth>;
}

function Payouts() {
  const queryClient = useQueryClient();
  const [tab, setTab] = useState<Tab>("draft");
  const [generating, setGenerating] = useState(false);
  const [periodEnd, setPeriodEnd] = useState(lastMonthEnd);
  const [result, setResult] = useState<{ created: number; skipped: { name: string; amount: number; reason: string }[] } | null>(null);
  const refresh = () => queryClient.invalidateQueries({ queryKey: ["admin", "payouts"] });

  const generate = async () => {
    setGenerating(true);
    try {
      const { data } = await mutate(() => api.POST("/admin/payouts/generate", { body: { period_end: periodEnd } }));
      setResult(data);
      await refresh();
      setTab("draft");
    } finally {
      setGenerating(false);
    }
  };

  return (
    <>
      <AdminPageHeader title="Payouts" description="Monthly instructor payouts: generate, have a second person approve, transfer, then record the UTR." />
      <div className="px-8 py-6">
        <div className="mb-6 flex flex-wrap items-end justify-between gap-4 border-b border-line">
          <div role="tablist" aria-label="Payouts" className="flex gap-6 overflow-x-auto overflow-y-hidden">
            {tabs.map((t) => (
              <button
                key={t.value}
                role="tab"
                aria-selected={tab === t.value}
                onClick={() => setTab(t.value)}
                className={cn("-mb-px shrink-0 border-b-2 pb-3 text-sm font-semibold", tab === t.value ? "border-gold-500 text-navy-900" : "border-transparent text-stone-500 hover:text-navy-900")}
              >
                {t.label}
              </button>
            ))}
          </div>
          <div className="mb-2 flex items-center gap-2">
            <Label htmlFor="period-end" className="text-xs text-stone-500">
              Earnings available up to
            </Label>
            <Input id="period-end" type="date" value={periodEnd} max={new Date().toLocaleDateString("en-CA")} onChange={(e) => setPeriodEnd(e.target.value)} className="h-8 w-40 bg-white" />
            <Button size="sm" onClick={generate} disabled={generating || !periodEnd}>
              {generating ? "Generating…" : "Generate payouts"}
            </Button>
          </div>
        </div>
        {tab === "accounts" ? <Accounts /> : <PayoutList status={tab} onChange={refresh} />}
      </div>

      <Dialog open={!!result} onOpenChange={(o) => !o && setResult(null)}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{result?.created ? `${result.created} payout${result.created === 1 ? "" : "s"} drafted` : "No new payouts"}</DialogTitle>
            <DialogDescription>Drafts need approval by someone other than you before they can be paid.</DialogDescription>
          </DialogHeader>
          {!!result?.skipped.length && (
            <ul className="max-h-64 space-y-2 overflow-y-auto text-sm">
              {result.skipped.map((s, i) => (
                <li key={i} className="flex justify-between gap-3 rounded-md bg-paper px-3 py-2">
                  <span>
                    <span className="font-semibold text-ink">{s.name}</span>
                    <span className="block text-xs text-stone-500">{s.reason}</span>
                  </span>
                  <span className="tabular text-stone-700">{formatPaise(s.amount, true)}</span>
                </li>
              ))}
            </ul>
          )}
          <DialogFooter>
            <Button onClick={() => setResult(null)}>Done</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function PayoutList({ status, onChange }: { status: Exclude<Tab, "accounts">; onChange: () => void }) {
  const list = useQuery({
    queryKey: ["admin", "payouts", status],
    queryFn: async () => (await unwrap(api.GET("/admin/payouts", { params: { query: { status } } }))).data,
  });
  const [paying, setPaying] = useState<Payout | null>(null);

  if (list.isPending) return <Skeleton className="h-40 w-full" />;
  if (list.isError) return <FormAlert>Couldn&rsquo;t load payouts.</FormAlert>;
  if (list.data.length === 0) return <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center text-sm text-stone-500">Nothing here.</div>;

  return (
    <>
      <ul className="space-y-3">
        {list.data.map((p) => (
          <PayoutCard key={p.id} payout={p} onChange={onChange} onPay={() => setPaying(p)} />
        ))}
      </ul>
      <MarkPaid payout={paying} onClose={() => setPaying(null)} onDone={onChange} />
    </>
  );
}

function PayoutCard({ payout: p, onChange, onPay }: { payout: Payout; onChange: () => void; onPay: () => void }) {
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [cancelling, setCancelling] = useState(false);
  const [reason, setReason] = useState("");

  const run = async (call: () => Promise<unknown>) => {
    setBusy(true);
    setError(null);
    try {
      await call();
      onChange();
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  };
  const path = { params: { path: { payout: p.id } } };

  return (
    <li className="rounded-md border border-line bg-white p-5 text-sm">
      <div className="flex flex-wrap items-start gap-4">
        <div className="min-w-0 flex-1">
          <p className="font-semibold text-ink">{p.instructor.name}</p>
          <p className="text-xs text-stone-500">
            {p.instructor.email} · to {p.bank.account_holder}, A/c XXXX{p.bank.account_last4}, IFSC {p.bank.ifsc}
          </p>
          <p className="mt-1 text-xs text-stone-500">
            Earnings to {formatDate(p.period_end)} · {p.entries_count} ledger lines · created {formatDate(p.created_at)}
            {p.reference && <> · UTR {p.reference}</>}
            {p.note && <> · {p.note}</>}
          </p>
        </div>
        <div className="text-right">
          <p className="tabular font-serif text-xl font-semibold text-navy-900">{formatPaise(p.net, true)}</p>
          <p className="tabular text-xs text-stone-500">
            {formatPaise(p.gross, true)} − TDS {p.tds_section} {p.tds_rate_bps / 100}% {formatPaise(p.tds, true)}
          </p>
          <div className="mt-1">
            <StatusPill status={p.status} />
          </div>
        </div>
      </div>

      {error && (
        <div className="mt-3">
          <FormAlert>{error}</FormAlert>
        </div>
      )}

      {(p.status === "draft" || p.status === "approved") && (
        <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-line pt-4">
          {p.status === "draft" &&
            (p.generated_by_me ? (
              <span className="inline-flex items-center gap-1.5 text-xs text-stone-500">
                <CircleAlert className="size-3.5" /> You generated this payout, so another finance admin must approve it.
              </span>
            ) : (
              <Button size="sm" disabled={busy} onClick={() => run(() => mutate(() => api.POST("/admin/payouts/{payout}/approve", path)))}>
                Approve
              </Button>
            ))}
          {p.status === "approved" && (
            <Button size="sm" onClick={onPay}>
              Record bank transfer
            </Button>
          )}
          {!cancelling ? (
            <Button size="sm" variant="ghost" className="text-destructive" onClick={() => setCancelling(true)}>
              Cancel payout
            </Button>
          ) : (
            <div className="flex w-full flex-wrap items-center gap-2">
              <Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Why? (the earnings return to the balance)" className="max-w-sm bg-white" />
              <Button size="sm" variant="destructive" disabled={busy || reason.trim().length < 5} onClick={() => run(() => mutate(() => api.POST("/admin/payouts/{payout}/cancel", { ...path, body: { reason: reason.trim() } })))}>
                Cancel payout
              </Button>
              <Button size="sm" variant="ghost" onClick={() => setCancelling(false)}>
                Keep
              </Button>
            </div>
          )}
        </div>
      )}
    </li>
  );
}

function MarkPaid({ payout, onClose, onDone }: { payout: Payout | null; onClose: () => void; onDone: () => void }) {
  const [reference, setReference] = useState("");
  const [paidOn, setPaidOn] = useState(() => new Date().toISOString().slice(0, 10));
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (!payout) return;
    setBusy(true);
    setError(null);
    try {
      await mutate(() => api.POST("/admin/payouts/{payout}/mark-paid", { params: { path: { payout: payout.id } }, body: { reference: reference.trim(), paid_on: paidOn } }));
      setReference("");
      onDone();
      onClose();
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={!!payout} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Record bank transfer</DialogTitle>
          <DialogDescription>
            {payout && (
              <>
                Transfer {formatPaise(payout.net, true)} to {payout.bank.account_holder} (A/c XXXX{payout.bank.account_last4}, {payout.bank.ifsc}), then record it here. The instructor is
                emailed.
              </>
            )}
          </DialogDescription>
        </DialogHeader>
        {error && <FormAlert>{error}</FormAlert>}
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="utr">UTR / transfer reference</Label>
            <Input id="utr" value={reference} onChange={(e) => setReference(e.target.value.toUpperCase())} className="font-mono" />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="paid-on">Transferred on</Label>
            <Input id="paid-on" type="date" value={paidOn} onChange={(e) => setPaidOn(e.target.value)} />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={submit} disabled={busy || reference.trim().length < 6}>
            {busy ? "Saving…" : "Mark as paid"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function Accounts() {
  const queryClient = useQueryClient();
  const list = useQuery({ queryKey: ["admin", "payouts", "accounts"], queryFn: async () => (await unwrap(api.GET("/admin/payout-accounts"))).data });

  if (list.isPending) return <Skeleton className="h-40 w-full" />;
  if (list.isError) return <FormAlert>Couldn&rsquo;t load payout details.</FormAlert>;
  if (list.data.length === 0) return <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center text-sm text-stone-500">Nothing waiting for verification.</div>;

  return (
    <ul className="space-y-3">
      {list.data.map((a) => (
        <AccountCard key={a.instructor.id} account={a} onDone={() => queryClient.invalidateQueries({ queryKey: ["admin", "payouts", "accounts"] })} />
      ))}
    </ul>
  );
}

function AccountCard({ account: a, onDone }: { account: Account; onDone: () => void }) {
  const [note, setNote] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const path = { params: { path: { user: a.instructor.id } } };

  const decide = async (verify: boolean) => {
    setBusy(true);
    setError(null);
    try {
      if (verify) await mutate(() => api.POST("/admin/payout-accounts/{user}/verify", path));
      else await mutate(() => api.POST("/admin/payout-accounts/{user}/reject", { ...path, body: { note: note.trim() } }));
      onDone();
    } catch (e) {
      setError(errorText(e));
      setBusy(false);
    }
  };

  return (
    <li className="rounded-md border border-line bg-white p-5 text-sm">
      <p className="font-semibold text-ink">{a.instructor.name}</p>
      <p className="text-xs text-stone-500">
        {a.instructor.email} · submitted {formatDate(a.submitted_at)}
      </p>
      <dl className="mt-3 grid gap-x-6 gap-y-1 text-xs sm:grid-cols-3">
        {[
          ["Name on PAN", a.legal_name],
          ["PAN", a.pan_masked],
          ["GSTIN", a.gstin ?? "—"],
          ["Account holder", a.account_holder],
          ["Account", a.account_masked],
          ["IFSC", a.ifsc],
        ].map(([k, v]) => (
          <div key={k}>
            <dt className="text-stone-500">{k}</dt>
            <dd className="font-medium text-ink">{v}</dd>
          </div>
        ))}
      </dl>
      <p className="mt-3 text-xs text-stone-500">Verify the name matches the PAN and the bank account (e.g. by a ₹1 penny-drop or a cancelled cheque) before approving.</p>
      {error && <div className="mt-3"><FormAlert>{error}</FormAlert></div>}
      <div className="mt-4 space-y-2 border-t border-line pt-4">
        <Textarea rows={2} value={note} onChange={(e) => setNote(e.target.value)} placeholder="Reason, if rejecting (sent to the instructor)" className="bg-white" />
        <div className="flex gap-2">
          <Button size="sm" disabled={busy} onClick={() => decide(true)}>
            Verify
          </Button>
          <Button size="sm" variant="destructive" disabled={busy || note.trim().length < 10} onClick={() => decide(false)}>
            Reject
          </Button>
        </div>
      </div>
    </li>
  );
}
