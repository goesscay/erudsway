"use client";

import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus } from "lucide-react";
import type { components, paths } from "@erudsway/api-client";
import { AdminPageHeader, formatDate, StatusPill } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { api, ApiRequestError, mutate, unwrap } from "@/lib/api/client";
import { formatPaise } from "@/lib/format";

type Coupon = components["schemas"]["CouponResource"];
type NewCoupon = NonNullable<paths["/admin/coupons"]["post"]["requestBody"]>["content"]["application/json"];

const couponsKey = ["admin", "coupons"];

function describe(c: Coupon): string {
  if (c.kind === "percent") return `${c.value}% off`;
  if (c.kind === "fixed") return `${formatPaise(c.value)} off`;
  return `Price ${formatPaise(c.value)}`;
}

export default function CouponsPage() {
  return <RequireAuth permission="manage_finance">{() => <Coupons />}</RequireAuth>;
}

function Coupons() {
  const queryClient = useQueryClient();
  const [creating, setCreating] = useState(false);
  const list = useQuery({ queryKey: couponsKey, queryFn: async () => (await unwrap(api.GET("/admin/coupons"))).data });

  const toggle = async (c: Coupon) => {
    await mutate(() => api.PATCH("/admin/coupons/{coupon}", { params: { path: { coupon: c.id } }, body: { is_active: !c.is_active } }));
    await queryClient.invalidateQueries({ queryKey: couponsKey });
  };

  return (
    <>
      <AdminPageHeader title="Coupons" description="Codes learners enter in the cart. Codes can be switched off but never edited once shared." />
      <div className="px-8 py-6">
        <div className="mb-5 flex justify-end">
          <Button onClick={() => setCreating(true)}>
            <Plus /> New coupon
          </Button>
        </div>
        {list.isPending ? (
          <Skeleton className="h-48 w-full" />
        ) : list.isError ? (
          <FormAlert>Couldn&rsquo;t load coupons.</FormAlert>
        ) : list.data.length === 0 ? (
          <div className="rounded-md border border-dashed border-line bg-white px-6 py-16 text-center text-sm text-stone-500">No coupons yet.</div>
        ) : (
          <div className="overflow-x-auto rounded-md border border-line bg-white">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-line bg-paper-2/60 text-xs font-semibold tracking-wide text-stone-500 uppercase">
                <tr>
                  <th className="px-5 py-3">Code</th>
                  <th className="px-5 py-3">Discount</th>
                  <th className="px-5 py-3">Applies to</th>
                  <th className="px-5 py-3">Used</th>
                  <th className="px-5 py-3">Valid until</th>
                  <th className="px-5 py-3">Status</th>
                  <th className="px-5 py-3">
                    <span className="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {list.data.map((c) => (
                  <tr key={c.id}>
                    <td className="px-5 py-3 font-mono font-semibold text-ink">{c.code}</td>
                    <td className="px-5 py-3 text-stone-700">{describe(c)}</td>
                    <td className="px-5 py-3 text-stone-700">{c.course?.title ?? "All courses"}</td>
                    <td className="tabular px-5 py-3 text-stone-700">
                      {c.redemptions}
                      {c.max_redemptions ? ` / ${c.max_redemptions}` : ""}
                    </td>
                    <td className="tabular px-5 py-3 text-stone-700">{c.ends_at ? formatDate(c.ends_at) : "No end date"}</td>
                    <td className="px-5 py-3">
                      <StatusPill status={c.is_active ? "active" : "inactive"} />
                    </td>
                    <td className="px-5 py-3 text-right">
                      <Button size="sm" variant="ghost" onClick={() => void toggle(c)}>
                        {c.is_active ? "Switch off" : "Switch on"}
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
      <CreateCoupon open={creating} onClose={() => setCreating(false)} />
    </>
  );
}

function CreateCoupon({ open, onClose }: { open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient();
  const [code, setCode] = useState("");
  const [kind, setKind] = useState<NewCoupon["kind"]>("percent");
  const [value, setValue] = useState("");
  const [course, setCourse] = useState("");
  const [max, setMax] = useState("");
  const [endsAt, setEndsAt] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setBusy(true);
    setError(null);
    const amount = Number(value);
    try {
      await mutate(() =>
        api.POST("/admin/coupons", {
          body: {
            code: code.trim(),
            kind,
            // Percent as typed; rupee amounts sent in paise.
            value: kind === "percent" ? amount : Math.round(amount * 100),
            course: course.trim() || null,
            max_redemptions: max ? Number(max) : null,
            ends_at: endsAt ? new Date(`${endsAt}T23:59:59+05:30`).toISOString() : null,
          },
        }),
      );
      await queryClient.invalidateQueries({ queryKey: couponsKey });
      setCode("");
      setValue("");
      setCourse("");
      setMax("");
      setEndsAt("");
      onClose();
    } catch (e) {
      setError(e instanceof ApiRequestError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>New coupon</DialogTitle>
          <DialogDescription>Platform-promoted codes pay the instructor 30% of net revenue (decision D2).</DialogDescription>
        </DialogHeader>
        {error && <FormAlert>{error}</FormAlert>}
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="c-code">Code</Label>
            <Input id="c-code" value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} placeholder="GSTLAUNCH" className="font-mono uppercase" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="c-kind">Type</Label>
              <select id="c-kind" value={kind} onChange={(e) => setKind(e.target.value as NewCoupon["kind"])} className="h-10 w-full rounded-md border border-input bg-white px-3 text-sm">
                <option value="percent">Percent off</option>
                <option value="fixed">Rupees off</option>
                <option value="fixed_price">Fixed price</option>
              </select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="c-value">{kind === "percent" ? "Percent" : "Amount (₹)"}</Label>
              <Input id="c-value" inputMode="numeric" value={value} onChange={(e) => setValue(e.target.value.replace(/[^\d.]/g, ""))} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="c-course">Course address (optional)</Label>
            <Input id="c-course" value={course} onChange={(e) => setCourse(e.target.value)} placeholder="Leave empty for every course" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="c-max">Total uses (optional)</Label>
              <Input id="c-max" inputMode="numeric" value={max} onChange={(e) => setMax(e.target.value.replace(/\D/g, ""))} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="c-ends">Valid until (optional)</Label>
              <Input id="c-ends" type="date" value={endsAt} onChange={(e) => setEndsAt(e.target.value)} />
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={submit} disabled={busy || code.trim().length < 4 || !value}>
            {busy ? "Creating…" : "Create coupon"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
