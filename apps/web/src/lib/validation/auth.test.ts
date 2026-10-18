import { describe, expect, it } from "vitest";
import { applicationSchema, registerSchema } from "./auth";

describe("registerSchema", () => {
  const valid = { name: "Asha Raman", email: "asha@example.com", password: "learning2026", password_confirmation: "learning2026" };

  it("accepts a valid registration", () => {
    expect(registerSchema.safeParse(valid).success).toBe(true);
  });

  it.each([
    ["short password", { password: "abc1", password_confirmation: "abc1" }, "password"],
    ["password without a number", { password: "abcdefgh", password_confirmation: "abcdefgh" }, "password"],
    ["mismatched confirmation", { password_confirmation: "different1" }, "password_confirmation"],
    ["invalid email", { email: "asha@" }, "email"],
  ])("rejects %s", (_, overrides, field) => {
    const result = registerSchema.safeParse({ ...valid, ...overrides });
    expect(result.success).toBe(false);
    expect(result.error?.issues.map((i) => i.path[0])).toContain(field);
  });
});

describe("applicationSchema", () => {
  const valid = {
    headline: "Chartered Accountant, GST trainer",
    topics: ["GST"],
    experience: "x".repeat(120),
    teaching_experience: "classroom" as const,
    sample_url: "",
  };

  it("accepts a valid application with no sample link", () => {
    expect(applicationSchema.safeParse(valid).success).toBe(true);
  });

  it("requires https sample links", () => {
    expect(applicationSchema.safeParse({ ...valid, sample_url: "http://example.com" }).success).toBe(false);
    expect(applicationSchema.safeParse({ ...valid, sample_url: "https://example.com/lesson" }).success).toBe(true);
  });

  it("limits topics to five", () => {
    expect(applicationSchema.safeParse({ ...valid, topics: ["a1", "b2", "c3", "d4", "e5", "f6"] }).success).toBe(false);
  });
});
