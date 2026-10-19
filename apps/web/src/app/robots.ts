import type { MetadataRoute } from "next";

const SITE = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: {
      userAgent: "*",
      allow: "/",
      // Private or personal areas, plus design-review mock-ups.
      disallow: ["/account", "/admin", "/instructor", "/learn", "/my-learning", "/cart", "/verify-email", "/reset-password", "/design-system"],
    },
    sitemap: `${SITE}/sitemap.xml`,
  };
}
