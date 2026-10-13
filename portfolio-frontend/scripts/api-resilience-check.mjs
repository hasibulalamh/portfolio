#!/usr/bin/env node
/**
 * Proves an API that misbehaves cannot hold a render — or a build — open.
 *
 * The production symptom this guards: `curl https://hasibulalam.com` returned
 * HTTP 000 after ~12 minutes. The container accepted the connection and never
 * answered, because a server-side fetch was waiting on an API that never
 * replied. A 429 is the same bug from the other side: the API answers
 * instantly, but every render still goes and asks it again. Neither is
 * survivable without a bounded fetch.
 *
 * The harness stands up a stub API in both states and drives the real thing —
 * a real `next build` (which prerenders the homepage through the real fetch
 * path) followed by a real `next start`:
 *
 *   1. rate limited — every public read answers 429 + Retry-After
 *   2. hung         — every public read accepts the connection and never answers
 *
 * Expected in both: the build and the requests finish in seconds, the rendered
 * document reports the fallback (`x-settings-source: fallback`, with the
 * hardcoded site title rather than the API's), /api-health answers with the
 * API's status and timing, and the server logged `api_fallback` with the
 * matching reason.
 *
 * Usage (nothing else may be serving this app):
 *   node scripts/api-resilience-check.mjs
 *
 * It rebuilds `.next/` against the stub URL — the same directory the E2E suite
 * and a dev server use — and leaves `.env.local` alone: NEXT_PUBLIC_API_URL is
 * exported for the build, exactly as Render's ARG/ENV bridge supplies it in
 * production. `node_modules` must be installed; no other prerequisites.
 *
 * Exit status: 0 when every scenario behaves, 1 otherwise.
 */

import { spawn } from 'node:child_process'
import fs from 'node:fs'
import http from 'node:http'
import path from 'node:path'
import process from 'node:process'

const STUB_PORT = 8099
const SITE_PORT = 3055
const ROOT = path.resolve(new URL('..', import.meta.url).pathname)
const CACHE_DIR = path.join(ROOT, '.next', 'cache')

// lib/api.js gives each fetch 8s. The stub hangs far longer than that, and the
// budgets here are wider than the ceilings they check, so a bound that holds is
// the timeout doing the work rather than the harness giving up first.
const STUB_HANG_MS = 120_000
const BUILD_BUDGET_MS = 180_000
const REQUEST_BUDGET_MS = 30_000

// The site title in lib/fallbacks.js. The API's own site_title would replace
// it, so finding this string means the fallback is what rendered. The escaped
// form is what actually appears in the HTML (`&` is encoded by the renderer).
const FALLBACK_TITLE = 'Hasibul Alam — Laravel & Vue.js Full-Stack Developer'
const FALLBACK_TITLE_HTML = FALLBACK_TITLE.replaceAll('&', '&amp;')

const scenarios = [
  {
    name: 'rate limited (429 + Retry-After)',
    expectedReason: 'http_429',
    respond(res) {
      res.writeHead(429, { 'Content-Type': 'application/json', 'Retry-After': '60' })
      res.end(JSON.stringify({ data: null, message: 'Too Many Requests', errors: null }))
    },
  },
  {
    name: 'hung (accepts, never answers)',
    expectedReason: 'timeout',
    respond(res) {
      // No response, deliberately: the socket stays open until the client gives
      // up, which is what a cold or wedged container does.
      setTimeout(() => {
        if (!res.writableEnded) {
          res.writeHead(504)
          res.end()
        }
      }, STUB_HANG_MS).unref()
    },
  },
]

let mode = 0
let failures = 0

function check(ok, label, detail = '') {
  console.log(`    ${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? ` — ${detail}` : ''}`)
  if (!ok) {
    failures += 1
  }
}

/** Run to completion inside a budget; rejects if the budget kills it. */
function run(command, args, env, budgetMs) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, { cwd: ROOT, env: { ...process.env, ...env }, stdio: ['ignore', 'pipe', 'pipe'] })
    let output = ''
    let timedOut = false

    const timer = setTimeout(() => {
      timedOut = true
      child.kill('SIGKILL')
    }, budgetMs)

    child.stdout.on('data', (chunk) => { output += chunk })
    child.stderr.on('data', (chunk) => { output += chunk })
    child.on('error', reject)
    child.on('close', (code) => {
      clearTimeout(timer)

      if (timedOut) {
        reject(new Error(`${command} ${args.join(' ')} did not finish within ${budgetMs}ms`))
        return
      }

      resolve({ code, output })
    })
  })
}

function startStub() {
  const server = http.createServer((req, res) => {
    scenarios[mode].respond(res)
  })

  return new Promise((resolve) => server.listen(STUB_PORT, '127.0.0.1', () => resolve(server)))
}

async function startSite(env) {
  const server = spawn('npx', ['next', 'start', '--port', String(SITE_PORT)], {
    cwd: ROOT,
    env: { ...process.env, ...env },
    stdio: ['ignore', 'pipe', 'pipe'],
  })
  let log = ''
  server.stdout.on('data', (chunk) => { log += chunk })
  server.stderr.on('data', (chunk) => { log += chunk })

  const deadline = Date.now() + 60_000
  while (Date.now() < deadline) {
    try {
      await fetch(`http://127.0.0.1:${SITE_PORT}/api-health`, { signal: AbortSignal.timeout(15_000) })
      return { server, log: () => log }
    } catch {
      await new Promise((resolve) => setTimeout(resolve, 500))
    }
  }

  server.kill('SIGKILL')
  throw new Error('next start never became ready')
}

async function timedFetch(url) {
  const startedAt = Date.now()

  try {
    const response = await fetch(url, { signal: AbortSignal.timeout(REQUEST_BUDGET_MS) })
    return { response, elapsed: Date.now() - startedAt, error: null }
  } catch (error) {
    return { response: null, elapsed: Date.now() - startedAt, error }
  }
}

async function main() {
  const stub = await startStub()
  const apiUrl = `http://127.0.0.1:${STUB_PORT}/api`
  const env = { NEXT_PUBLIC_API_URL: apiUrl, NEXT_PUBLIC_BASE_URL: `http://127.0.0.1:${SITE_PORT}` }

  try {
    for (const [index, scenario] of scenarios.entries()) {
      mode = index
      // Drop the ISR/Data Cache so this build is a real render rather than a
      // copy of the previous scenario's output.
      fs.rmSync(CACHE_DIR, { recursive: true, force: true })

      console.log(`\n  ${index + 1}. API ${scenario.name}`)

      // 1. A full prerender (the homepage is fetched during the build).
      console.log(`    building against ${apiUrl} ...`)
      const startedAt = Date.now()
      let build

      try {
        build = await run('npx', ['next', 'build'], env, BUILD_BUDGET_MS)
      } catch (error) {
        check(false, 'build completes', error.message)
        continue
      }

      const buildMs = Date.now() - startedAt
      check(build.code === 0, 'build completes', `exit ${build.code} in ${buildMs}ms`)

      if (build.code !== 0) {
        // The output is the only thing that says *why*; without it a failing
        // build reports nothing but an exit code.
        console.log(build.output.split('\n').slice(-20).map((line) => `      | ${line}`).join('\n'))
      }
      check(buildMs < BUILD_BUDGET_MS, 'build bounded by the fetch timeout', `${buildMs}ms`)
      check(build.output.includes('"event":"api_fallback"'), 'api_fallback logged during the prerender')
      check(
        build.output.includes(`"reason":"${scenario.expectedReason}"`),
        `log reason is ${scenario.expectedReason}`,
        build.output.match(/"reason":"[^"]+"/)?.[0] ?? 'no reason logged',
      )

      // 2. The served page and the live probe.
      const site = await startSite(env)

      try {
        const page = await timedFetch(`http://127.0.0.1:${SITE_PORT}/`)

        if (page.error) {
          check(false, 'page still renders', `aborted after ${page.elapsed}ms: ${page.error.message}`)
        } else {
          const html = await page.response.text()

          check(page.response.status === 200, 'page still renders (200)', `${page.response.status} in ${page.elapsed}ms`)
          check(page.elapsed < REQUEST_BUDGET_MS, 'page bounded by the fetch timeout', `${page.elapsed}ms`)
          check(/x-settings-source"[^>]*fallback/.test(html), 'document reports the fallback')
          check(
            html.includes(FALLBACK_TITLE) || html.includes(FALLBACK_TITLE_HTML),
            'fallback content is what rendered (site title)',
          )
          // From FALLBACK_SETTINGS.copyright_text — admin-managed copy the API
          // would have supplied instead.
          check(html.includes('Made with'), 'fallback footer copy rendered')
        }

        const health = await timedFetch(`http://127.0.0.1:${SITE_PORT}/api-health`)

        if (health.error) {
          check(false, '/api-health answers', `aborted after ${health.elapsed}ms: ${health.error.message}`)
        } else {
          const body = await health.response.json()

          check(health.elapsed < REQUEST_BUDGET_MS, '/api-health answers within the timeout', `${health.elapsed}ms`)
          check(
            body.api_status === (scenario.expectedReason === 'http_429' ? 429 : null),
            `api_status reports the failure (${scenario.expectedReason === 'http_429' ? '429' : 'null'})`,
            JSON.stringify({ api_status: body.api_status, api_response_ms: body.api_response_ms, api_error: body.api_error }),
          )
        }
      } finally {
        site.server.kill('SIGTERM')
      }
    }
  } finally {
    stub.close()
  }

  console.log(failures === 0 ? '\nAll resilience checks passed.' : `\n${failures} check(s) failed.`)
  process.exit(failures === 0 ? 0 : 1)
}

main().catch((error) => {
  console.error(error)
  process.exit(1)
})
