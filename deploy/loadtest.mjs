#!/usr/bin/env node
// Erudsway — small load test, no dependencies (Node 20+).
//
//   node deploy/loadtest.mjs https://new.erudsway.com [--duration 60] [--concurrency 20]
//   (locally the API is elsewhere: add --api http://localhost:8000)
//
// Mixes the traffic a launch brings: catalogue API, course pages (web), course detail API,
// the health check, and starting playback of free-preview lectures (token + master playlist).
// Checkout is left out on purpose: it would create real orders at Razorpay.
//
// Rate limits apply per IP, so from one machine expect some 429s on playback (60/min) —
// they're counted separately and show the limits working, not the server failing.

const args = process.argv.slice(2);
const base = (args.find((a) => /^https?:\/\//.test(a)) ?? "http://localhost:3000").replace(/\/$/, "");
const opt = (name, dflt) => {
  const i = args.indexOf(`--${name}`);
  return i >= 0 ? Number(args[i + 1]) : dflt;
};
const DURATION = opt("duration", 60) * 1000;
const CONCURRENCY = opt("concurrency", 20);
const apiIndex = args.indexOf("--api");
const api = `${apiIndex >= 0 ? args[apiIndex + 1].replace(/\/$/, "") : base}/api/v1`;

async function json(url) {
  const res = await fetch(url, { headers: { Accept: "application/json" } });
  if (!res.ok) throw new Error(`${url} → ${res.status}`);
  return res.json();
}

console.log(`Target ${base} · ${CONCURRENCY} concurrent · ${DURATION / 1000}s`);
const catalog = await json(`${api}/courses?per_page=24`);
const slugs = catalog.data.map((c) => c.slug);
if (slugs.length === 0) throw new Error("No published courses to test with.");
const previews = [];
for (const slug of slugs.slice(0, 6)) {
  const detail = await json(`${api}/courses/${slug}`).catch(() => null);
  for (const s of detail?.data?.curriculum ?? []) {
    for (const l of s.lectures) if (l.is_free_preview && l.kind === "video") previews.push(l.id);
  }
}
const pick = (a) => a[Math.floor(Math.random() * a.length)];

/** Weighted scenarios: [weight, name, request]. */
const scenarios = [
  [30, "web course page", () => fetch(`${base}/course/${pick(slugs)}`)],
  [15, "web home", () => fetch(`${base}/`)],
  [20, "api catalogue search", () => fetch(`${api}/courses?per_page=12&sort=${pick(["popular", "newest", "rating"])}`, { headers: { Accept: "application/json" } })],
  [20, "api course detail", () => fetch(`${api}/courses/${pick(slugs)}`, { headers: { Accept: "application/json" } })],
  [5, "api health", () => fetch(`${api}/health`)],
  ...(previews.length
    ? [[10, "playback (token + playlist)", async () => {
        const res = await fetch(`${api}/lectures/${pick(previews)}/playback`, { method: "POST", headers: { Accept: "application/json" } });
        if (!res.ok) return res;
        const body = await res.json();
        return fetch(body.data.playlist_url ?? body.data.master_url);
      }]]
    : []),
];
const totalWeight = scenarios.reduce((t, s) => t + s[0], 0);
const choose = () => {
  let r = Math.random() * totalWeight;
  for (const s of scenarios) if ((r -= s[0]) < 0) return s;
  return scenarios[0];
};

const stats = new Map();
const record = (name, ms, status) => {
  const s = stats.get(name) ?? { times: [], ok: 0, limited: 0, errors: 0, codes: {} };
  s.times.push(ms);
  if (status >= 200 && status < 400) s.ok++;
  else if (status === 429) s.limited++;
  else {
    s.errors++;
    s.codes[status] = (s.codes[status] ?? 0) + 1;
  }
  stats.set(name, s);
};

const end = Date.now() + DURATION;
async function worker() {
  while (Date.now() < end) {
    const [, name, run] = choose();
    const t = performance.now();
    let status = 0;
    try {
      const res = await run();
      status = res.status;
      await res.arrayBuffer();
    } catch {
      status = 0;
    }
    record(name, performance.now() - t, status);
  }
}
const started = Date.now();
await Promise.all(Array.from({ length: CONCURRENCY }, worker));
const secs = (Date.now() - started) / 1000;

const pct = (a, p) => {
  const s = [...a].sort((x, y) => x - y);
  return Math.round(s[Math.min(s.length - 1, Math.floor((p / 100) * s.length))] ?? 0);
};
let total = 0;
let failed = 0;
console.log("\nscenario                       reqs   ok  429  err    p50    p95    p99 (ms)");
for (const [name, s] of stats) {
  total += s.times.length;
  failed += s.errors;
  console.log(
    `${name.padEnd(30)} ${String(s.times.length).padStart(5)} ${String(s.ok).padStart(4)} ${String(s.limited).padStart(4)} ${String(s.errors).padStart(4)} ${String(pct(s.times, 50)).padStart(6)} ${String(pct(s.times, 95)).padStart(6)} ${String(pct(s.times, 99)).padStart(6)}` +
      (s.errors ? `  ${JSON.stringify(s.codes)}` : ""),
  );
}
console.log(`\n${total} requests in ${secs.toFixed(0)}s = ${(total / secs).toFixed(1)} req/s · ${failed} errors (non-2xx/3xx, excluding 429)`);
process.exit(failed > total * 0.01 ? 1 : 0);
