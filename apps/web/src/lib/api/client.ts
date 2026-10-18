import { createApiClient, type ApiError } from "@erudsway/api-client";

export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export const api = createApiClient({ baseUrl: `${API_URL}/api/v1` });

/** Thrown for any non-2xx API response; carries the envelope's code and field errors. */
export class ApiRequestError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly fields: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = "ApiRequestError";
  }
}

let csrfReady: Promise<void> | null = null;

/** Sanctum sets the XSRF-TOKEN cookie; fetched once per page load before the first write. */
export function ensureCsrfCookie(): Promise<void> {
  csrfReady ??= fetch(`${API_URL}/sanctum/csrf-cookie`, { credentials: "include" }).then((r) => {
    if (!r.ok) {
      csrfReady = null;
      throw new ApiRequestError(r.status, "CSRF_FAILED", "Could not reach the server. Please try again.");
    }
  });
  return csrfReady;
}

/** Session ended or rotated (login/logout): the next write must fetch a fresh token. */
export function resetCsrf() {
  csrfReady = null;
}

type Result<T> = { data?: T; error?: unknown; response: Response };

/**
 * Unwraps an openapi-fetch result: returns `data` or throws ApiRequestError.
 * Usage: `const me = await unwrap(api.GET("/me"))`.
 */
export async function unwrap<T>(promise: Promise<Result<T>>): Promise<T> {
  let result: Result<T>;
  try {
    result = await promise;
  } catch {
    throw new ApiRequestError(0, "NETWORK_ERROR", "We couldn't reach Erudsway. Check your connection and try again.");
  }

  const { data, error, response } = result;
  if (response.ok) return data as T;

  const envelope = (error as Partial<ApiError> | undefined)?.error;
  throw new ApiRequestError(
    response.status,
    envelope?.code ?? "UNKNOWN",
    envelope?.message ?? "Something went wrong. Please try again.",
    envelope?.fields ?? {},
  );
}

/** Same as unwrap, but makes sure the CSRF cookie exists first (for POST/PUT/PATCH/DELETE). */
export async function mutate<T>(call: () => Promise<Result<T>>): Promise<T> {
  await ensureCsrfCookie();
  try {
    return await unwrap(call());
  } catch (e) {
    // 419 = CSRF token mismatch (expired session): refresh the token and retry once.
    if (e instanceof ApiRequestError && e.status === 419) {
      resetCsrf();
      await ensureCsrfCookie();
      return unwrap(call());
    }
    throw e;
  }
}
