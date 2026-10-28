import type { paths } from "@erudsway/api-client";
import { API_URL } from "@/lib/api/client";
import { postForm } from "@/lib/authoring/editor";

export type TicketSummary =
  paths["/me/support/tickets"]["get"]["responses"][200]["content"]["application/json"]["data"]["items"][number];
export type TicketDetail =
  paths["/me/support/tickets/{ticket}"]["get"]["responses"][200]["content"]["application/json"]["data"];
export type TicketMessage = TicketDetail["messages"][number];

export const MAX_FILES = 3;
export const MAX_FILE_BYTES = 5 * 1024 * 1024;
export const ACCEPT = ".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf";

/** Checks the files a learner picked against the server's limits; returns a message or null. */
export function checkFiles(files: File[]): string | null {
  if (files.length > MAX_FILES) return `Attach up to ${MAX_FILES} files.`;
  for (const f of files) {
    if (!/\.(png|jpe?g|pdf)$/i.test(f.name)) return `${f.name}: only PNG, JPG or PDF files.`;
    if (f.size > MAX_FILE_BYTES) return `${f.name} is larger than 5 MB.`;
  }
  return null;
}

export function attachmentUrl(id: string) {
  return `${API_URL}/api/v1/support/attachments/${id}`;
}

/** Sends a ticket or reply with its attachments as multipart form data. */
export function postWithFiles<T>(
  path: string,
  fields: Record<string, string | number | boolean | null>,
  files: File[],
) {
  const form = new FormData();
  for (const [key, value] of Object.entries(fields)) {
    if (value === null || value === "") continue;
    form.append(key, typeof value === "boolean" ? (value ? "1" : "0") : String(value));
  }
  files.forEach((f) => form.append("attachments[]", f));
  return postForm<T>(path, form);
}

export function formatBytes(n: number) {
  return n >= 1024 * 1024 ? `${(n / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`;
}
