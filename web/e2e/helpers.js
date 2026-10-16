import { execFileSync } from 'node:child_process'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect } from '@playwright/test'

export const API = 'http://127.0.0.1:8472/api'

/** Creates a fresh demo account through the API and signs the page in with its token. */
export async function demoSignIn(page, request) {
  const res = await request.post(`${API}/auth/demo`, { headers: { Accept: 'application/json' }, data: { accept_terms: true } })
  expect(res.status()).toBe(201)
  const { token } = await res.json()
  await page.addInitScript((t) => localStorage.setItem('rr_token', t), token)
  return token
}

// Same database and binary as the API server in playwright.config.js.
const artisanEnv = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: join(tmpdir(), 'review-responder-e2e.sqlite'), CACHE_STORE: 'array' }

function artisan(...args) {
  return execFileSync(process.env.PHP_BINARY || 'php', [join(process.cwd(), '../api/artisan'), ...args], { env: artisanEnv, encoding: 'utf8' })
}

/**
 * Real (non-demo) accounts can only be created by Google sign-in, so tests create one directly in the e2e database.
 * `admin` promotes it with the user:promote command and records some API usage so the admin tables have rows; `acceptedTerms: false` leaves the first-sign-in confirmation pending.
 */
export async function realSignIn(page, { admin = false, acceptedTerms = true } = {}) {
  const email = `e2e-${Date.now()}-${Math.random().toString(36).slice(2, 8)}@example.com`
  const out = artisan(
    'tinker',
    '--execute',
    `$u = App\\Models\\User::create(['name' => 'E2E Owner', 'email' => '${email}']);` +
      (acceptedTerms ? "$u->forceFill(['terms_accepted_at' => now()])->save();" : '') +
      (admin
        ? "foreach ([['google', 'reviews.list', true], ['llm', 'chat.draft', false], ['mail', 'summary.send', true]] as [$s, $a, $ok]) { App\\Models\\ApiUsageLog::create(['service' => $s, 'action' => $a, 'ok' => $ok, 'duration_ms' => 1234]); }"
        : '') +
      "echo PHP_EOL.$u->createToken('spa')->plainTextToken.PHP_EOL;",
  )
  const token = out.split(/\r?\n/).find((line) => /^\d+\|\w+$/.test(line.trim()))?.trim()
  expect(token, `no token in tinker output: ${out}`).toBeTruthy()
  if (admin) artisan('user:promote', email)
  await page.addInitScript((t) => localStorage.setItem('rr_token', t), token)
  return email
}

/** The first draft card in the queue that still has its reply editor. */
export function firstDraftCard(page) {
  return page.locator('main li').filter({ has: page.getByRole('button', { name: 'Approve & Post' }) }).first()
}

export async function expectNoHorizontalOverflow(page) {
  const { scroll, client } = await page.evaluate(() => ({
    scroll: document.documentElement.scrollWidth,
    client: document.documentElement.clientWidth,
  }))
  expect(scroll, `page is ${scroll - client}px wider than the viewport`).toBe(client)
}
