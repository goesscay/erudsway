"use client";

import { useState } from "react";
import { RotateCcw } from "lucide-react";
import { AdminPageHeader, formatDate } from "@/components/admin/page-header";
import { RequireAuth } from "@/components/auth/require-auth";
import { FormAlert, TextInput } from "@/components/forms/field";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useSettings, type Setting } from "@/lib/admin/api";
import { ApiRequestError } from "@/lib/api/client";
import { formatPaise } from "@/lib/format";

export default function SettingsPage() {
  return <RequireAuth permission="manage_settings">{() => <SettingsView />}</RequireAuth>;
}

type Value = string | number | boolean | null;

/** What the person types, from the stored value (bps → %, paise → ₹). */
function toInput(s: Setting, v: Value): string | boolean {
  if (s.type === "boolean") return Boolean(v);
  if (v === null || v === undefined) return "";
  if (s.type === "bps") return String(Number(v) / 100);
  if (s.type === "paise") return String(Number(v) / 100);
  return String(v);
}

/** Back to the stored form. */
function fromInput(s: Setting, input: string | boolean): Value {
  if (s.type === "boolean") return Boolean(input);
  const text = String(input).trim();
  if (s.type === "bps" || s.type === "paise") return text === "" ? "" : Math.round(Number(text) * 100);
  if (s.type === "integer") return text === "" ? "" : Number(text);
  return text;
}

function display(s: Setting, v: Value): string {
  if (v === null || v === "") return "not set";
  if (s.type === "boolean") return v ? "on" : "off";
  if (s.type === "bps") return `${Number(v) / 100}%`;
  if (s.type === "paise") return formatPaise(Number(v), true);
  return String(v);
}

function SettingsView() {
  const { query, save } = useSettings();
  const [edits, setEdits] = useState<Record<string, string | boolean>>({});
  const [fields, setFields] = useState<Record<string, string[]>>({});
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  if (query.isPending) return <Skeleton className="m-8 h-96" />;
  if (query.isError) return <FormAlert>Couldn&rsquo;t load settings.</FormAlert>;

  const items = query.data;
  const changed = items.filter((s) => s.key in edits && fromInput(s, edits[s.key]!) !== s.value);
  const groups = [...new Set(items.map((s) => s.group))];

  const send = (values: Record<string, Value>, summary: string) => {
    if (!window.confirm(summary)) return;
    setError(null);
    setFields({});
    setSaved(false);
    save.mutate(values, {
      onSuccess: () => {
        setEdits({});
        setSaved(true);
      },
      onError: (e) => {
        if (e instanceof ApiRequestError) {
          setError(e.message);
          setFields(Object.fromEntries(Object.entries(e.fields).map(([k, v]) => [k.replace(/^values\./, ""), v])));
        } else setError("Couldn’t save the settings.");
      },
    });
  };

  const saveAll = () =>
    send(
      Object.fromEntries(changed.map((s) => [s.key, fromInput(s, edits[s.key]!)])),
      `Change ${changed.length === 1 ? "this setting" : `these ${changed.length} settings`}?\n\n` +
        changed
          .map((s) => `• ${s.label}: ${display(s, s.value)} → ${display(s, fromInput(s, edits[s.key]!))}`)
          .join("\n") +
        "\n\nThe change applies straight away and is recorded in the audit log.",
    );

  return (
    <>
      <AdminPageHeader
        title="Settings"
        description="Values that used to need a deploy. Changes apply at once and are recorded in the audit log."
      >
        <Button onClick={saveAll} disabled={changed.length === 0 || save.isPending}>
          {save.isPending
            ? "Saving…"
            : changed.length > 0
              ? `Save ${changed.length} change${changed.length === 1 ? "" : "s"}`
              : "Save changes"}
        </Button>
      </AdminPageHeader>

      <div className="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-8">
        {error && <FormAlert>{error}</FormAlert>}
        {saved && <FormAlert tone="success">Saved. The new values are in use now.</FormAlert>}

        {groups.map((group) => (
          <section key={group} className="rounded-md border border-line bg-white">
            <h2 className="border-b border-line px-5 py-3.5 font-sans text-base font-bold text-ink">{group}</h2>
            <div className="divide-y divide-line">
              {items
                .filter((s) => s.group === group)
                .map((s) => {
                  const input = s.key in edits ? edits[s.key]! : toInput(s, s.value);
                  const id = `setting-${s.key}`;
                  const err = fields[s.key]?.[0];
                  return (
                    <div key={s.key} className="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_240px] sm:items-start">
                      <div>
                        <label htmlFor={id} className="text-sm font-semibold text-ink">
                          {s.label}
                        </label>
                        <p className="mt-0.5 text-xs leading-relaxed text-stone-500">{s.help}</p>
                        <p className="mt-1 text-xs text-stone-500">
                          {s.overridden ? (
                            <>
                              Changed here{s.updated_at && ` on ${formatDate(s.updated_at)}`} · default{" "}
                              {display(s, s.default)}{" "}
                              <button
                                type="button"
                                className="inline-flex items-center gap-1 font-semibold text-navy-800 hover:underline"
                                onClick={() =>
                                  send(
                                    { [s.key]: null },
                                    `Reset “${s.label}” to its default (${display(s, s.default)})?`,
                                  )
                                }
                              >
                                <RotateCcw className="size-3" /> Reset
                              </button>
                            </>
                          ) : (
                            "Using the default from the server configuration"
                          )}
                        </p>
                      </div>
                      <div>
                        {s.type === "boolean" ? (
                          <label className="flex h-10 items-center gap-2 text-sm text-stone-700">
                            <input
                              id={id}
                              type="checkbox"
                              checked={Boolean(input)}
                              onChange={(e) => setEdits({ ...edits, [s.key]: e.target.checked })}
                              className="size-4 accent-[var(--navy-900)]"
                            />
                            {input ? "On" : "Off"}
                          </label>
                        ) : (
                          <div className="relative">
                            {s.type === "paise" && (
                              <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-stone-500">
                                ₹
                              </span>
                            )}
                            <TextInput
                              id={id}
                              inputMode={s.type === "text" ? undefined : "decimal"}
                              value={String(input)}
                              aria-invalid={!!err}
                              onChange={(e) => setEdits({ ...edits, [s.key]: e.target.value })}
                              className={`h-10 text-sm ${s.type === "paise" ? "pl-7" : ""} ${s.type === "bps" ? "pr-8" : ""}`}
                            />
                            {s.type === "bps" && (
                              <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-stone-500">
                                %
                              </span>
                            )}
                            {s.type === "integer" && s.key.endsWith("days") && (
                              <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-stone-500">
                                days
                              </span>
                            )}
                          </div>
                        )}
                        {err && <p className="mt-1 text-xs font-medium text-destructive">{err}</p>}
                      </div>
                    </div>
                  );
                })}
            </div>
          </section>
        ))}
        <p className="text-xs text-stone-500">
          Payment gateway keys and other secrets stay in the server&rsquo;s environment file and can&rsquo;t be changed
          here.
        </p>
      </div>
    </>
  );
}
