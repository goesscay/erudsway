import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { Rating } from "./rating";

describe("Rating", () => {
  it("announces the rating to assistive technology", () => {
    render(<Rating value={4.7} count={18342} />);

    expect(screen.getByRole("img", { name: "Rated 4.7 out of 5" })).toBeInTheDocument();
    expect(screen.getByText("4.7")).toBeInTheDocument();
    expect(screen.getByText("(18,342)")).toBeInTheDocument();
  });

  it("omits the count when not provided", () => {
    render(<Rating value={3} />);

    expect(screen.queryByText(/\(/)).not.toBeInTheDocument();
  });
});
