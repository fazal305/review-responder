import { expect, test } from '@playwright/test'
import { demoSignIn, expectNoHorizontalOverflow, realSignIn } from './helpers'

test('save reply guidelines and email settings', async ({ page, request }) => {
  await demoSignIn(page, request)
  await page.goto('/app/settings')
  await expect(page).toHaveTitle('Settings · Review Responder')
  await expect(page.getByText('Demo account — not connected to any Google Business Profile.')).toBeVisible()

  const signOff = page.getByLabel('Sign-off')
  await signOff.fill('x'.repeat(121))
  await page.getByRole('button', { name: 'Save settings' }).click()
  await expect(page.getByText('Keep this to 120 characters or fewer.')).toBeVisible()
  await expect(signOff).toBeFocused()
  await expect(signOff).toHaveAttribute('aria-invalid', 'true')

  await signOff.fill('— The Sample Bakery team')
  await page.getByLabel('Tone').selectOption('formal')
  await page.getByLabel(/^Strict/).check()
  await page.getByLabel('Phrases to avoid').fill('valued customer')
  await page.getByLabel('Send at').selectOption('8')
  await page.getByRole('button', { name: 'Save settings' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Settings saved.' })).toBeVisible()

  await page.reload()
  await expect(page.getByLabel('Sign-off')).toHaveValue('— The Sample Bakery team')
  await expect(page.getByLabel('Tone')).toHaveValue('formal')
  await expect(page.getByLabel(/^Strict/)).toBeChecked()
  await expect(page.getByLabel('Send at')).toHaveValue('8')
})

test('a new Google account must confirm age, authority and terms first', async ({ page }) => {
  await realSignIn(page, { acceptedTerms: false })
  await page.goto('/app')

  await expect(page.getByRole('heading', { name: 'Before you start' })).toBeVisible()
  await page.getByRole('button', { name: 'Continue' }).click()
  await expect(page.getByText('Please tick the box to continue.')).toBeVisible()

  await page.getByLabel(/I am 18 or older, I am authorized to manage/).check()
  await page.getByRole('button', { name: 'Continue' }).click()
  await expect(page.getByRole('heading', { name: "This morning's queue" })).toBeVisible()
})

test('owners see a 403 page for the admin area', async ({ page, request }) => {
  await demoSignIn(page, request)
  await page.goto('/app/admin')
  await expect(page.getByRole('heading', { name: "You don't have access to this page" })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Admin' })).toHaveCount(0)
})

test('public pages have their own titles and footer links', async ({ page }) => {
  await page.goto('/')
  await expect(page).toHaveTitle('Review Responder — reply drafts for your Google reviews')
  const footer = page.getByRole('navigation', { name: 'Legal' })
  await footer.getByRole('link', { name: 'Privacy' }).click()
  await expect(page).toHaveTitle('Privacy Policy · Review Responder')
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', 'https://review-responder.vercel.app/privacy')
  await footer.getByRole('link', { name: 'Terms' }).click()
  await expect(page).toHaveTitle('Terms of Service · Review Responder')
  await footer.getByRole('link', { name: 'FAQ' }).click()
  await expect(page).toHaveTitle('FAQ · Review Responder')
  await expect(page.getByText('© 2026 Fazal Abbas')).toBeVisible()
})

test.describe('mobile', () => {
  test.use({ viewport: { width: 375, height: 812 } })

  for (const [path, heading] of [
    ['/', 'Your Google reviews, answered by breakfast.'],
    ['/signin', 'Sign in'],
    ['/auth/callback#error=denied', 'Sign in'],
    ['/privacy', 'Privacy Policy'],
    ['/terms', 'Terms of Service'],
    ['/faq', 'Frequently asked questions'],
    ['/no-such-page', "This page doesn't exist"],
  ]) {
    test(`${path} fits a 375px screen`, async ({ page }) => {
      await page.goto(path)
      await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible()
      await expectNoHorizontalOverflow(page)
    })
  }

  test('settings fits a 375px screen', async ({ page, request }) => {
    await demoSignIn(page, request)
    await page.goto('/app/settings')
    await expect(page.getByLabel('Sign-off')).toBeVisible()
    await expectNoHorizontalOverflow(page)
  })

  test('app 404, 403 and terms screens fit a 375px screen', async ({ page, request }) => {
    await demoSignIn(page, request)
    await page.goto('/app/no-such-page')
    await expect(page.getByRole('heading', { name: "This page doesn't exist" })).toBeVisible()
    await expectNoHorizontalOverflow(page)

    await page.goto('/app/admin')
    await expect(page.getByRole('heading', { name: "You don't have access to this page" })).toBeVisible()
    await expectNoHorizontalOverflow(page)
  })

  test('first-sign-in confirmation fits a 375px screen', async ({ page }) => {
    await realSignIn(page, { acceptedTerms: false })
    await page.goto('/app')
    await expect(page.getByRole('heading', { name: 'Before you start' })).toBeVisible()
    await expectNoHorizontalOverflow(page)
  })

  test('admin page fits a 375px screen', async ({ page }) => {
    await realSignIn(page, { admin: true })
    await page.goto('/app/admin')
    await expect(page.getByRole('heading', { name: 'System health' })).toBeVisible()
    await expect(page.getByRole('region', { name: /API calls in the last 7 days/ })).toBeVisible()
    await expect(page.getByRole('region', { name: 'Number of drafts in each status, all time' })).toBeVisible()
    await expectNoHorizontalOverflow(page)
  })
})
