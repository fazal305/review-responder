import { Link, Outlet } from 'react-router'
import { Wordmark } from '../components/Logo'
import { OfflineBanner } from '../components/OfflineBanner'
import { useAuth } from '../lib/auth'

export default function PublicLayout() {
  const { status } = useAuth()
  return (
    <div className="flex min-h-dvh flex-col">
      <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-surface focus:px-3 focus:py-2">
        Skip to content
      </a>
      <OfflineBanner />
      <header className="border-b border-line">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
          <Link to="/" aria-label="Review Responder home">
            <Wordmark />
          </Link>
          <nav aria-label="Account" className="text-sm font-semibold">
            {status === 'authed' ? (
              <Link to="/app" className="rounded-lg bg-brand px-4 py-2 text-white hover:bg-brand-strong">
                Open app
              </Link>
            ) : (
              <Link to="/signin" className="rounded-lg px-3 py-2 hover:bg-sunken">
                Sign in
              </Link>
            )}
          </nav>
        </div>
      </header>
      <main id="main" className="flex-1">
        <Outlet />
      </main>
      <footer className="border-t border-line">
        <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-6 text-sm text-muted sm:px-6">
          <p>© 2026 Fazal Abbas</p>
          <nav aria-label="Legal">
            <ul className="flex flex-wrap gap-x-5 gap-y-2">
              <li>
                <Link to="/privacy" className="hover:text-ink hover:underline">
                  Privacy
                </Link>
              </li>
              <li>
                <Link to="/terms" className="hover:text-ink hover:underline">
                  Terms
                </Link>
              </li>
              <li>
                <Link to="/faq" className="hover:text-ink hover:underline">
                  FAQ
                </Link>
              </li>
            </ul>
          </nav>
        </div>
      </footer>
    </div>
  )
}
