import { API_URL, ApiRequestError, ensureCsrfCookie } from "@/lib/api/client";
import { readXsrfCookie } from "@/lib/api/client";
import { parseResponse } from "./editor";

interface UploadSession {
  id: string;
  chunk_size: number;
  total_chunks: number;
  received_chunks: number[];
}

export interface UploadProgress {
  sentBytes: number;
  totalBytes: number;
}

const CONCURRENCY = 3;
const RETRIES = 4;
const storageKey = (lectureId: number, file: File) => `erudsway:upload:${lectureId}:${file.name}:${file.size}:${file.lastModified}`;

/**
 * Resumable chunked upload. If the tab closes or the network drops, picking the same file
 * again for the same lecture resumes from the chunks the server already has.
 */
export async function uploadVideo(
  lectureId: number,
  file: File,
  onProgress: (p: UploadProgress) => void,
  signal?: AbortSignal,
): Promise<void> {
  await ensureCsrfCookie();
  const session = await resumeOrStart(lectureId, file);
  const received = new Set(session.received_chunks);
  let sent = [...received].reduce((n, i) => n + chunkBytes(session, file, i), 0);
  onProgress({ sentBytes: sent, totalBytes: file.size });

  const queue = Array.from({ length: session.total_chunks }, (_, i) => i).filter((i) => !received.has(i));

  const worker = async () => {
    for (let index = queue.shift(); index !== undefined; index = queue.shift()) {
      const blob = file.slice(index * session.chunk_size, (index + 1) * session.chunk_size);
      await withRetries(() => putChunk(session.id, index, blob, signal), signal);
      sent += blob.size;
      onProgress({ sentBytes: sent, totalBytes: file.size });
    }
  };
  await Promise.all(Array.from({ length: Math.min(CONCURRENCY, queue.length) }, worker));

  await call(`/instructor/uploads/${session.id}/complete`, { method: "POST" });
  try {
    localStorage.removeItem(storageKey(lectureId, file));
  } catch {
    /* storage unavailable */
  }
}

async function resumeOrStart(lectureId: number, file: File): Promise<UploadSession> {
  let saved: string | null = null;
  try {
    saved = localStorage.getItem(storageKey(lectureId, file));
  } catch {
    /* storage unavailable */
  }
  if (saved) {
    try {
      return (await call<{ data: UploadSession }>(`/instructor/uploads/${saved}`, { method: "GET" })).data;
    } catch {
      /* expired or completed: start over */
    }
  }

  const res = await call<{ data: UploadSession }>(`/instructor/lectures/${lectureId}/video-uploads`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ file_name: file.name, file_size: file.size, mime: file.type || guessMime(file.name) }),
  });
  try {
    localStorage.setItem(storageKey(lectureId, file), res.data.id);
  } catch {
    /* storage unavailable */
  }
  return res.data;
}

async function putChunk(sessionId: string, index: number, blob: Blob, signal?: AbortSignal) {
  await call(`/instructor/uploads/${sessionId}/chunks/${index}`, {
    method: "PUT",
    headers: { "Content-Type": "application/octet-stream" },
    body: blob,
    signal,
  });
}

async function withRetries(fn: () => Promise<void>, signal?: AbortSignal) {
  for (let attempt = 0; ; attempt++) {
    try {
      return await fn();
    } catch (e) {
      const retriable = !(e instanceof ApiRequestError) || e.status >= 500 || e.status === 429 || e.status === 0;
      if (signal?.aborted || !retriable || attempt >= RETRIES) throw e;
      await new Promise((r) => setTimeout(r, 1000 * 2 ** attempt));
    }
  }
}

async function call<T = unknown>(path: string, init: RequestInit): Promise<T> {
  let res: Response;
  try {
    res = await fetch(`${API_URL}/api/v1${path}`, {
      ...init,
      credentials: "include",
      headers: { Accept: "application/json", "X-XSRF-TOKEN": readXsrfCookie(), ...(init.headers ?? {}) },
    });
  } catch (e) {
    if (init.signal?.aborted) throw e;
    throw new ApiRequestError(0, "NETWORK_ERROR", "Connection lost. The upload will resume when you try again.");
  }
  return parseResponse<T>(res);
}

function chunkBytes(s: UploadSession, file: File, index: number) {
  return Math.min(s.chunk_size, file.size - index * s.chunk_size);
}

function guessMime(name: string): string {
  const ext = name.split(".").pop()?.toLowerCase();
  return ({ mp4: "video/mp4", mov: "video/quicktime", mkv: "video/x-matroska", webm: "video/webm", avi: "video/x-msvideo" } as Record<string, string>)[ext ?? ""] ?? "video/mp4";
}
