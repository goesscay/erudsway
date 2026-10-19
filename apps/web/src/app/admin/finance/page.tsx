"use client";

import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Download } from "lucide-react";
import { AdminPageHeader, formatDate } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { API_URL, api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { formatPaise } from "@/lib/format";

const channelLabel = { organic: "Organic (marketplace)", instructor_referral: "Instructor's own link or coupon", platform_promoted: "Platform promotion" } as const;
type Channel = keyof typeof channelLabel;

function monthStart(): string {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 1).toLocaleDateString("en-CA");
}

export default function FinancePage() {
  return (
    <RequireAuth permission="manage_finance">
      {() => (
        <>
          <AdminPageHeader title="Finance" description="Sales, GST, fees and instructor earnings for a period, straight from the ledger." />
          <div className="space-y-10 px-8 py-6">
            <Summary />
            <Rules />
          </div>
        </>
      )}
    </RequireAuth>
  );
}

function Summary() {
  const [from, setFrom] = useState(monthStart);
  const [to, setTo] = useState(() => new Date().toLocaleDateString("en-CA"));
  const report = useQuery({
    queryKey: ["admin", "finance", from, to],
    queryFn: async () => (await unwrap(api.GET("/admin/finance/summary", { params: { query: { from, to } } }))).data,
  });
  const r = report.data;

  return (
    <section className="space-y-5">
      <div className="flex flex-wrap items-end gap-3">
        <div className="space-y-1">
          <Label htmlFor="from" className="text-xs">
            From
          </Label>
          <Input id="from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-40 bg-white" />
        </div>
        <div className="space-y-1">
          <Label htmlFor="to" className="text-xs">
            To
          </Label>
          <Input id="to" type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-40 bg-white" />
        </div>
        <a href={`${API_URL}/api/v1/admin/finance/ledger.csv?from=${from}&to=${to}`} className={buttonVariants({ variant: "outline", className: "ml-auto" })}>
          <Download /> Ledger CSV
        </a>
      </div>

      {report.isPending ? (
        <Skeleton className="h-48 w-full" />
      ) : report.isError || !r ? (
        <FormAlert>Couldn&rsquo;t load the report.</FormAlert>
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card label="Collected" value={r.sales.collected} hint={`${r.sales.count} course sales · ${formatPaise(r.sales.discounts, true)} in discounts`} />
            <Card label="Refunded" value={r.refunds.amount} hint={`${r.refunds.count} refunds`} />
            <Card label="Instructor earnings" value={r.split.instructor} hint="Net of refunds" />
            <Card label="Platform revenue" value={r.split.platform} hint="Net of refunds and absorbed fees" />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <Card label="GST collected (net)" value={r.split.tax} hint="Liability to file in GSTR-1 / GSTR-3B" />
            <Card label="Gateway fees" value={r.split.gateway_fee} hint="Razorpay fees incl. GST on fees" />
          </div>

          <div className="overflow-x-auto rounded-md border border-line bg-white">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-line bg-paper-2/60 text-xs font-semibold tracking-wide text-stone-500 uppercase">
                <tr>
                  <th className="px-5 py-3">Instructor</th>
                  <th className="px-5 py-3 text-right">Sales</th>
                  <th className="px-5 py-3 text-right">Collected</th>
                  <th className="px-5 py-3 text-right">Earnings</th>
                  <th className="px-5 py-3 text-right">On hold</th>
                  <th className="px-5 py-3 text-right">Available</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {r.instructors.length === 0 && (
                  <tr>
                    <td colSpan={6} className="px-5 py-10 text-center text-stone-500">
                      No sales in this period.
                    </td>
                  </tr>
                )}
                {r.instructors.map((i) => (
                  <tr key={i.id}>
                    <td className="px-5 py-3 font-semibold text-ink">{i.name}</td>
                    <td className="tabular px-5 py-3 text-right">{i.sales}</td>
                    <td className="tabular px-5 py-3 text-right">{formatPaise(i.collected, true)}</td>
                    <td className="tabular px-5 py-3 text-right">{formatPaise(i.earnings, true)}</td>
                    <td className="tabular px-5 py-3 text-right text-stone-500">{formatPaise(i.pending, true)}</td>
                    <td className="tabular px-5 py-3 text-right font-semibold">{formatPaise(i.available, true)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </section>
  );
}

function Card({ label, value, hint }: { label: string; value: number; hint: string }) {
  return (
    <div className="rounded-md border border-line bg-white p-5">
      <p className="text-xs font-semibold tracking-wide text-stone-500 uppercase">{label}</p>
      <p className="tabular mt-2 font-serif text-2xl font-semibold text-navy-900">{formatPaise(value, true)}</p>
      <p className="mt-1 text-xs text-stone-500">{hint}</p>
    </div>
  );
}

function Rules() {
  const queryClient = useQueryClient();
  const rules = useQuery({ queryKey: ["admin", "commission-rules"], queryFn: async () => (await unwrap(api.GET("/admin/commission-rules"))).data });
  const [channel, setChannel] = useState<Channel>("organic");
  const [email, setEmail] = useState("");
  const [percent, setPercent] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const refresh = () => queryClient.invalidateQueries({ queryKey: ["admin", "commission-rules"] });

  const save = async () => {
    setBusy(true);
    setError(null);
    try {
      await mutate(() => api.POST("/admin/commission-rules", { body: { channel, instructor_email: email.trim() || null, instructor_percent: Number(percent) } }));
      setEmail("");
      setPercent("");
      await refresh();
    } catch (e) {
      setError(e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <section>
      <h2 className="text-h3">Commission rules</h2>
      <p className="mt-1 max-w-[70ch] text-sm text-stone-500">
        The instructor&rsquo;s share of net revenue (after GST and gateway fees). Without a rule, the defaults apply: 50% organic, 90% instructor&rsquo;s own link or coupon,
        30% platform promotion. Each sale keeps the rate in force when it was made.
      </p>

      <div className="mt-5 flex flex-wrap items-end gap-3 rounded-md border border-line bg-white p-4">
        <div className="space-y-1">
          <Label htmlFor="r-channel" className="text-xs">
            Channel
          </Label>
          <select id="r-channel" value={channel} onChange={(e) => setChannel(e.target.value as Channel)} className="h-10 rounded-md border border-input bg-white px-3 text-sm">
            {Object.entries(channelLabel).map(([v, l]) => (
              <option key={v} value={v}>
                {l}
              </option>
            ))}
          </select>
        </div>
        <div className="space-y-1">
          <Label htmlFor="r-email" className="text-xs">
            Instructor email (empty = everyone)
          </Label>
          <Input id="r-email" value={email} onChange={(e) => setEmail(e.target.value)} className="w-64 bg-white" />
        </div>
        <div className="space-y-1">
          <Label htmlFor="r-pct" className="text-xs">
            Instructor share %
          </Label>
          <Input id="r-pct" inputMode="decimal" value={percent} onChange={(e) => setPercent(e.target.value.replace(/[^\d.]/g, ""))} className="w-28 bg-white" />
        </div>
        <Button onClick={save} disabled={busy || percent === ""}>
          Set rule
        </Button>
        {error && <p className="w-full text-xs text-destructive">{error}</p>}
      </div>

      {rules.data && rules.data.length > 0 && (
        <ul className="mt-4 divide-y divide-line rounded-md border border-line bg-white text-sm">
          {rules.data.map((r) => (
            <li key={r.id} className="flex flex-wrap items-center gap-4 px-5 py-3">
              <span className="min-w-0 flex-1">
                <span className="font-semibold text-ink">{r.instructor_percent}%</span> · {channelLabel[r.channel]} · {r.instructor?.name ?? "Everyone"}
                <span className="block text-xs text-stone-500">
                  From {formatDate(r.effective_from)}
                  {r.effective_to && <> to {formatDate(r.effective_to)}</>}
                </span>
              </span>
              {r.active ? (
                <Button size="sm" variant="ghost" onClick={() => void mutate(() => api.POST("/admin/commission-rules/{rule}/end", { params: { path: { rule: r.id } } })).then(refresh)}>
                  End rule
                </Button>
              ) : (
                <span className="text-xs text-stone-500">Ended</span>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
