import { describe, expect, it } from "vitest";
import { hasPermission, isStaff, safeNext, type CurrentUser } from "./session";

const user = (permissions: string[]) => ({ permissions }) as unknown as CurrentUser;

describe("safeNext", () => {
  it("keeps same-site relative paths", () => {
    expect(safeNext("/teach#apply")).toBe("/teach#apply");
  });

  it.each([
    ["absolute URL", "https://evil.example/phish"],
    ["protocol-relative URL", "//evil.example"],
    ["javascript URL", "javascript:alert(1)"],
    ["empty", ""],
    ["missing", null],
  ])("falls back for %s", (_, value) => {
    expect(safeNext(value)).toBe("/my-learning");
  });
});

describe("permissions", () => {
  it("checks a specific permission", () => {
    expect(hasPermission(user(["manage_users"]), "manage_users")).toBe(true);
    expect(hasPermission(user(["manage_users"]), "manage_finance")).toBe(false);
    expect(hasPermission(null, "manage_users")).toBe(false);
  });

  it("treats anyone with a permission as staff", () => {
    expect(isStaff(user(["review_instructor_applications"]))).toBe(true);
    expect(isStaff(user([]))).toBe(false);
  });
});
