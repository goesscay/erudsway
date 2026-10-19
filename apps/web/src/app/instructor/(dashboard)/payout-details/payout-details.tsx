"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { BadgeCheck, Clock3, Lock } from "lucide-react";
import { formatDate } from "@/components/admin/page-header";
import { Field, FormAlert, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import type { components } from "@erudsway/api-client";
import { api, mutate, unwrap } from "@/lib/api/client";
import { applyApiErrors } from "@/lib/api/form-errors";
import { useMe } from "@/lib/auth/session";
import { payoutSchema, type PayoutValues } from "@/lib/validation/payout";

const key = ["instructor", "payout-account"];

export function PayoutDetails() {
  const { data: me } = useMe();
  const account = useQuery({ queryKey: key, enabled: !!me?.instructor, queryFn: async () => (await unwrap(api.GET("/instructor/payout-account"))).data });
  const [editing, setEditing] = useState(false);

  return (
    <>
      <header className="border-b border-line bg-white px-4 py-6 sm:px-8">
        <h1 className="text-h2">Payout details</h1>
        <p className="mt-1 text-sm text-stone-500">Where we send your earnings. Needed before your first payout; checked by our finance team.</p>
      </header>
      <div className="max-w-2xl px-4 py-8 sm:px-8">
        {account.isPending ? (
          <Skeleton className="h-64 w-full" />
        ) : account.data && !editing ? (
          <Summary account={account.data} onEdit={() => setEditing(true)} />
        ) : (
          <DetailsForm hasExisting={!!account.data} onDone={() => setEditing(false)} />
        )}
      </div>
    </>
  );
}

type Account = components["schemas"]["PayoutAccountResource"];

function Summary({ account, onEdit }: { account: Account; onEdit: () => void }) {
  return (
    <div className="space-y-5">
      {account.status === "verified" && (
        <FormAlert tone="success">
          <span className="inline-flex items-center gap-1.5">
            <BadgeCheck className="size-4" /> Verified {account.verified_at && <>on {formatDate(account.verified_at)}</>}. Payouts go to this account.
          </span>
        </FormAlert>
      )}
      {account.status === "submitted" && (
        <FormAlert tone="info">
          <span className="inline-flex items-center gap-1.5">
            <Clock3 className="size-4" /> Submitted {formatDate(account.submitted_at)} — our finance team will verify it within two working days.
          </span>
        </FormAlert>
      )}
      {account.status === "rejected" && <FormAlert>We couldn&rsquo;t verify these details: {account.review_note}</FormAlert>}

      <dl className="divide-y divide-line rounded-md border border-line bg-white text-sm">
        {[
          ["Name on PAN", account.legal_name],
          ["PAN", account.pan_masked],
          ["GSTIN", account.gstin ?? "—"],
          ["Account holder", account.account_holder],
          ["Account number", account.account_masked],
          ["IFSC", account.ifsc],
        ].map(([label, value]) => (
          <div key={label} className="flex justify-between gap-4 px-5 py-3">
            <dt className="text-stone-500">{label}</dt>
            <dd className="font-medium text-ink">{value}</dd>
          </div>
        ))}
      </dl>
      <Button variant="outline" onClick={onEdit}>
        Change details
      </Button>
      <p className="text-xs text-stone-500">Changing your bank account pauses payouts until the new details are verified.</p>
    </div>
  );
}

function DetailsForm({ hasExisting, onDone }: { hasExisting: boolean; onDone: () => void }) {
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);
  const form = useForm<PayoutValues>({
    resolver: zodResolver(payoutSchema),
    defaultValues: { legal_name: "", pan: "", gstin: "", account_holder: "", account_number: "", account_number_confirmation: "", ifsc: "" },
  });
  const { errors, isSubmitting } = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      const { data } = await mutate(() => api.PUT("/instructor/payout-account", { body: { ...values, gstin: values.gstin || null } }));
      queryClient.setQueryData(key, data);
      void queryClient.invalidateQueries({ queryKey: ["instructor", "revenue"] });
      onDone();
    } catch (e) {
      setFormError(applyApiErrors(e, form.setError, ["legal_name", "pan", "gstin", "account_holder", "account_number", "account_number_confirmation", "ifsc"]));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-5 rounded-md border border-line bg-white p-6">
      {formError && <FormAlert>{formError}</FormAlert>}
      {hasExisting && <FormAlert tone="info">For your security we never show saved numbers in full — enter the details again to change them.</FormAlert>}
      <Field label="Name as on PAN" error={errors.legal_name?.message}>
        {(p) => <TextInput {...p} autoComplete="name" {...form.register("legal_name")} />}
      </Field>
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="PAN" error={errors.pan?.message}>
          {(p) => <TextInput {...p} maxLength={10} autoComplete="off" className="uppercase" {...form.register("pan")} />}
        </Field>
        <Field label="GSTIN" optional error={errors.gstin?.message} hint="If you're registered for GST">
          {(p) => <TextInput {...p} maxLength={15} autoComplete="off" className="uppercase" {...form.register("gstin")} />}
        </Field>
      </div>
      <Field label="Account holder name" error={errors.account_holder?.message}>
        {(p) => <TextInput {...p} autoComplete="off" {...form.register("account_holder")} />}
      </Field>
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="Account number" error={errors.account_number?.message}>
          {(p) => <TextInput {...p} inputMode="numeric" autoComplete="off" {...form.register("account_number")} />}
        </Field>
        <Field label="Re-enter account number" error={errors.account_number_confirmation?.message}>
          {(p) => <TextInput {...p} inputMode="numeric" autoComplete="off" onPaste={(e) => e.preventDefault()} {...form.register("account_number_confirmation")} />}
        </Field>
      </div>
      <Field label="IFSC" error={errors.ifsc?.message}>
        {(p) => <TextInput {...p} maxLength={11} autoComplete="off" className="uppercase" {...form.register("ifsc")} />}
      </Field>
      <div className="flex flex-wrap items-center gap-3">
        <Button type="submit" disabled={isSubmitting}>
          <Lock /> {isSubmitting ? "Saving…" : "Submit for verification"}
        </Button>
        {hasExisting && (
          <Button type="button" variant="ghost" onClick={onDone}>
            Cancel
          </Button>
        )}
      </div>
      <p className="text-xs text-stone-500">PAN and account number are encrypted and only used to pay you and to report TDS.</p>
    </form>
  );
}
