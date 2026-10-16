import { expect } from '@playwright/test'

export const API = 'http://127.0.0.1:8472/api'

/** Creates a fresh demo account through the API and signs the page in with its token. */
export async function demoSignIn(page, request) {
  const res = await request.post(`${API}/auth/demo`, { headers: { Accept: 'application/json' } })
  expect(res.status()).toBe(201)
  const { token } = await res.json()
  await page.addInitScript((t) => localStorage.setItem('rr_token', t), token)
  return token
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
