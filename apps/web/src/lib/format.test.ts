import { describe, expect, it } from "vitest";
import { formatClock, formatDuration, formatPaise, initials } from "./format";

describe("formatPaise", () => {
  it("formats integer paise as whole rupees", () => {
    expect(formatPaise(49900)).toBe("₹499");
  });

  it("uses Indian digit grouping", () => {
    expect(formatPaise(12345600)).toBe("₹1,23,456");
  });
});

describe("formatDuration", () => {
  it("shows minutes under an hour", () => {
    expect(formatDuration(905)).toBe("15 min");
  });

  it("shows hours and minutes", () => {
    expect(formatDuration(61 * 3600 + 1800)).toBe("61 hr 30 min");
  });

  it("drops zero minutes", () => {
    expect(formatDuration(3 * 3600)).toBe("3 hr");
  });
});

describe("formatClock", () => {
  it("pads seconds", () => {
    expect(formatClock(305)).toBe("5:05");
  });
});

describe("initials", () => {
  it("takes the first two name parts", () => {
    expect(initials("Vikram Sethi, CA")).toBe("VS");
  });

  it("skips professional prefixes", () => {
    expect(initials("CA Vikram Sethi")).toBe("VS");
    expect(initials("Adv. Farah Qureshi")).toBe("FQ");
    expect(initials("CS Rohan Deshpande")).toBe("RD");
  });
});
