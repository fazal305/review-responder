import { expect, test } from '@playwright/test'
import { demoSignIn, expectNoHorizontalOverflow, firstDraftCard } from './helpers'

test('demo sign-in shows sample drafts grouped by location', async ({ page }) => {
  await page.goto('/signin')
  await page.getByRole('button', { name: 'Try the demo' }).click()
  await expect(page.getByText('Please confirm you are 18 or older and accept the Terms to try the demo.')).toBeVisible()
  await expect(page).toHaveURL(/\/signin$/)

  await page.getByLabel('I am 18 or older and accept the Terms of Service').check()
  await page.getByRole('button', { name: 'Try the demo' }).click()

  await expect(page).toHaveURL(/\/app$/)
  await expect(page.getByRole('heading', { name: "This morning's queue" })).toBeVisible()
  await expect(page.getByRole('heading', { level: 2, name: /^Sample Bakery/ }).first()).toBeVisible()
  await expect(page.getByText('Sample data').first()).toBeVisible()
  await expect(page.getByText(/out of 5 stars/).first()).toBeVisible()
  await expect(page.getByText('Template draft (no AI key configured)').first()).toBeVisible()
})

test('edit a draft, then approve and post it', async ({ page, request }) => {
  await demoSignIn(page, request)
  await page.goto('/app')

  const card = firstDraftCard(page)
  const reviewer = await card.getByRole('heading', { level: 3 }).textContent()
  const sameReviewer = page.getByRole('heading', { level: 3, name: reviewer, exact: true })
  const before = await sameReviewer.count()
  const reply = card.getByLabel('Your reply')

  await reply.fill('   ')
  await card.getByRole('button', { name: 'Approve & Post' }).click()
  await expect(card.getByText('Write a reply before posting.')).toBeVisible()
  await expect(reply).toBeFocused()

  await reply.fill('Thank you for the visit — see you soon.')
  await expect(card.getByText('Unsaved changes')).toBeVisible()
  await card.getByRole('button', { name: 'Approve & Post' }).click()

  await expect(page.getByRole('status').filter({ hasText: 'Reply posted.' })).toBeVisible()
  await expect(sameReviewer).toHaveCount(before - 1)
  await expect(page.getByText('1 posted this session')).toBeVisible()
})

test('regenerate a draft with a tone', async ({ page, request }) => {
  await demoSignIn(page, request)
  await page.goto('/app')

  const card = firstDraftCard(page)
  const toggle = card.getByRole('button', { name: 'Regenerate' })
  await toggle.click()
  await expect(toggle).toHaveAttribute('aria-expanded', 'true')
  await page.keyboard.press('Escape')
  await expect(toggle).toHaveAttribute('aria-expanded', 'false')
  await expect(toggle).toBeFocused()

  await toggle.click()
  await card.getByRole('button', { name: 'More apologetic' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'New draft ready.' })).toBeVisible()
  await expect(card.getByText('Template draft (no AI key configured) · More apologetic')).toBeVisible()
})

test('dismiss a draft after confirming', async ({ page, request }) => {
  await demoSignIn(page, request)
  await page.goto('/app')

  const card = firstDraftCard(page)
  const reviewer = await card.getByRole('heading', { level: 3 }).textContent()
  const sameReviewer = page.getByRole('heading', { level: 3, name: reviewer, exact: true })
  const before = await sameReviewer.count()
  await card.getByRole('button', { name: 'Dismiss' }).click()

  const dialog = page.getByRole('dialog', { name: 'Dismiss this draft?' })
  await expect(dialog).toBeVisible()
  await dialog.getByRole('button', { name: 'Cancel' }).click()
  await expect(sameReviewer).toHaveCount(before)

  await card.getByRole('button', { name: 'Dismiss' }).click()
  await dialog.getByRole('button', { name: 'Dismiss draft' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'Draft dismissed.' })).toBeVisible()
  await expect(sameReviewer).toHaveCount(before - 1)
})

test.describe('mobile', () => {
  test.use({ viewport: { width: 375, height: 812 } })

  test('queue and locations fit a 375px screen', async ({ page, request }) => {
    await demoSignIn(page, request)

    await page.goto('/app')
    await expect(firstDraftCard(page)).toBeVisible()
    await expectNoHorizontalOverflow(page)

    await firstDraftCard(page).getByRole('button', { name: 'Regenerate' }).click()
    await expectNoHorizontalOverflow(page)

    await page.goto('/app/locations')
    await expect(page.getByRole('heading', { name: 'Locations' })).toBeVisible()
    await expect(page.getByRole('switch').first()).toBeVisible()
    await expectNoHorizontalOverflow(page)
  })
})
