import { existsSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { defineConfig, devices } from '@playwright/test'

const API_PORT = 8472
const WEB_PORT = 5482
const PHP = process.env.PHP_BINARY || 'php'

// A throwaway SQLite database outside the repo, so the e2e run needs no MySQL.
const DB = join(tmpdir(), 'review-responder-e2e.sqlite')
if (!existsSync(DB)) writeFileSync(DB, '')

export default defineConfig({
  testDir: './e2e',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  reporter: 'list',
  use: {
    baseURL: `http://localhost:${WEB_PORT}`,
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: [
    {
      command: `${PHP} ../api/artisan migrate --force && ${PHP} -S 127.0.0.1:${API_PORT} -t ../api/public ../api/public/index.php`,
      url: `http://127.0.0.1:${API_PORT}/up`,
      reuseExistingServer: true,
      env: {
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: DB,
        // Per-request array cache: each test signs in to a fresh demo account without tripping the demo sign-in limit.
        CACHE_STORE: 'array',
        QUEUE_CONNECTION: 'sync',
        GOOGLE_DRIVER: 'demo',
        DEMO_LOGIN_ENABLED: 'true',
        LLM_API_KEY: '',
        FRONTEND_URL: `http://localhost:${WEB_PORT}`,
      },
    },
    {
      command: `npm run dev -- --port ${WEB_PORT}`,
      url: `http://localhost:${WEB_PORT}`,
      reuseExistingServer: true,
      env: { VITE_API_URL: `http://127.0.0.1:${API_PORT}/api` },
    },
  ],
})
