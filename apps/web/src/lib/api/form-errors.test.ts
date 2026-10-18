import { describe, expect, it, vi } from "vitest";
import { ApiRequestError } from "./client";
import { applyApiErrors } from "./form-errors";

type Values = { email: string; password: string; topics: string[] };

describe("applyApiErrors", () => {
  it("maps field errors onto known fields and returns no form message", () => {
    const setError = vi.fn();
    const error = new ApiRequestError(422, "VALIDATION_FAILED", "Some fields need your attention.", {
      email: ["An account with this email already exists."],
    });

    expect(applyApiErrors<Values>(error, setError, ["email", "password"])).toBeNull();
    expect(setError).toHaveBeenCalledWith("email", { type: "server", message: "An account with this email already exists." });
  });

  it("maps nested array errors to their root field", () => {
    const setError = vi.fn();
    const error = new ApiRequestError(422, "VALIDATION_FAILED", "x", { "topics.0": ["Too long."] });

    applyApiErrors<Values>(error, setError, ["topics"]);

    expect(setError).toHaveBeenCalledWith("topics", { type: "server", message: "Too long." });
  });

  it("returns the API message for non-field errors", () => {
    const error = new ApiRequestError(403, "ACCOUNT_SUSPENDED", "This account has been suspended.");

    expect(applyApiErrors<Values>(error, vi.fn(), ["email"])).toBe("This account has been suspended.");
  });

  it("explains rate limiting in plain language", () => {
    const error = new ApiRequestError(429, "TOO_MANY_REQUESTS", "Too many attempts.");

    expect(applyApiErrors<Values>(error, vi.fn(), ["email"])).toBe("Too many attempts. Please wait a minute and try again.");
  });

  it("handles unexpected errors", () => {
    expect(applyApiErrors<Values>(new Error("boom"), vi.fn(), [])).toBe("Something went wrong. Please try again.");
  });
});
