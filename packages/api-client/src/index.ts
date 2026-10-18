import createClient, { type Middleware } from "openapi-fetch";
import type { paths } from "./schema";

export type { paths, components } from "./schema";

/** Uniform error shape returned by every /api/v1 endpoint. */
export interface ApiError {
  error: {
    code: string;
    message: string;
    fields?: Record<string, string[]>;
  };
}

export interface ApiClientOptions {
  baseUrl: string;
  /** Extra headers, e.g. the incoming Cookie header when rendering on the server. */
  headers?: Record<string, string>;
  fetch?: typeof globalThis.fetch;
}

function readCookie(name: string): string | undefined {
  if (typeof document === "undefined") return undefined;
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : undefined;
}

/** Sends Sanctum's XSRF token back on state-changing browser requests. */
const csrf: Middleware = {
  onRequest({ request }) {
    if (request.method !== "GET" && request.method !== "HEAD") {
      const token = readCookie("XSRF-TOKEN");
      if (token) request.headers.set("X-XSRF-TOKEN", token);
    }
    return request;
  },
};

export function createApiClient({ baseUrl, headers, fetch }: ApiClientOptions) {
  const client = createClient<paths>({
    baseUrl,
    credentials: "include",
    headers: { Accept: "application/json", ...headers },
    fetch,
  });
  client.use(csrf);
  return client;
}

export type ApiClient = ReturnType<typeof createApiClient>;
