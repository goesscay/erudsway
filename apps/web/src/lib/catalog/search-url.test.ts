import { describe, expect, it } from "vitest";
import { browseHref, listParam, readSearchParams, toggleListValue } from "./search-url";

describe("readSearchParams", () => {
  it("keeps known keys, takes the first of repeated values and drops unknown keys", () => {
    expect(readSearchParams({ q: "gst", level: ["beginner", "advanced"], utm_source: "x", sort: undefined })).toEqual({
      q: "gst",
      level: "beginner",
    });
  });
});

describe("browseHref", () => {
  it("adds a filter and resets pagination", () => {
    expect(browseHref("/courses", { q: "tds", page: "3" }, { price: "free" })).toBe("/courses?q=tds&price=free");
  });

  it("keeps the page when paginating", () => {
    expect(browseHref("/courses", { q: "tds" }, { page: "2" })).toBe("/courses?q=tds&page=2");
  });

  it("removes a filter with null", () => {
    expect(browseHref("/courses/gst", { rating: "4.5", sort: "rating" }, { rating: null })).toBe("/courses/gst?sort=rating");
  });

  it("never puts the category in the query string (it lives in the path)", () => {
    expect(browseHref("/courses/gst", { category: "gst" }, {})).toBe("/courses/gst");
  });
});

describe("list params", () => {
  it("splits comma lists", () => {
    expect(listParam("beginner,advanced")).toEqual(["beginner", "advanced"]);
    expect(listParam(undefined)).toEqual([]);
  });

  it("toggles values on and off", () => {
    expect(toggleListValue(["beginner"], "advanced")).toBe("beginner,advanced");
    expect(toggleListValue(["beginner", "advanced"], "beginner")).toBe("advanced");
    expect(toggleListValue(["beginner"], "beginner")).toBeNull();
  });
});
