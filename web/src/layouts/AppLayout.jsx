import { Link, Navigate, NavLink, Outlet, useLocation } from 'react-router'
import { LogoMark, Wordmark } from '../components/Logo'
import { OfflineBanner } from '../components/OfflineBanner'
import { Button, ErrorState, Skeleton } from '../components/ui'
import { useAuth } from '../lib/auth'
import Forbidden from '../pages/public/Forbidden'

const NAV = [
  { to: '/app', label: 'Queue', end: true, icon: 'M4 5h16v10H14l-4 4v-4H4zM8 9h8M8 12h5' },
  { to: '/app/locations', label: 'Locations', icon: 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zm0-9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5' },
  { to: '/app/settings', label: 'Settings', icon: 'M4 7h10M18 7h2M4 17h4M12 17h8M16 5v4M10 15v4' },
  { to: '/app/admin', label: 'Admin', adminOnly: true, icon: 'M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z' },
]

function NavIcon({ d }) {
  return (
    <svg viewBox="0 0 24 24" className="size-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d={d} />
    </svg>
  )
}

export function RequireAuth({ children }) {
  const { status, retry, expired } = useAuth()
  const location = useLocation()

  if (status === 'checking') {
    return (
      <div className="mx-auto max-w-5xl space-y-4 p-6" aria-busy="true">
        <span className="sr-only" role="status">Loading your account…</span>
        <Skeleton className="h-10 w-48" />
        <Skeleton className="h-32" />
        <Skeleton className="h-64" />
      </div>
    )
  }
  if (status === 'unreachable') {
    return (
      <div className="mx-auto max-w-lg p-6 pt-20">
        <ErrorState title="Can't reach Review Responder" message="We couldn't contact the server to restore your session. Check your connection and try again." onRetry={retry} />
      </div>
    )
  }
  if (status !== 'authed') {
    return <Navigate to="/signin" replace state={{ from: location.pathname, expired }} />
  }
  return children
}

export function RequireAdmin({ children }) {
  const { user } = useAuth()
  return user?.role === 'admin' ? children : <Forbidden inApp />
}

export default function AppLayout() {
  const { user, signOut } = useAuth()
  const nav = NAV.filter((item) => !item.adminOnly || user?.role === 'admin')

  return (
    <div className="min-h-dvh lg:grid lg:grid-cols-[15rem_minmax(0,1fr)]">
      <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-surface focus:px-3 focus:py-2">
        Skip to content
      </a>

      <aside className="hidden border-r border-line bg-surface/70 lg:sticky lg:top-0 lg:flex lg:h-dvh lg:flex-col">
        <Link to="/app" className="px-5 py-5" aria-label="Review Responder queue">
          <Wordmark />
        </Link>
        <nav aria-label="Main" className="flex-1 space-y-1 px-3">
          {nav.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium ${isActive ? 'bg-brand-tint text-brand-strong' : 'text-muted hover:bg-sunken hover:text-ink'}`
              }
            >
              <NavIcon d={item.icon} />
              {item.label}
            </NavLink>
          ))}
        </nav>
        <div className="border-t border-line p-4">
          <p className="truncate text-sm font-semibold">{user?.name}</p>
          <p className="truncate text-xs text-muted">{user?.is_demo ? 'Demo account' : user?.email}</p>
          <Button variant="ghost" className="mt-2 -ml-2 min-h-8 px-2" onClick={signOut}>
            Sign out
          </Button>
        </div>
      </aside>

      <div className="flex min-w-0 flex-col pb-[calc(4.5rem+env(safe-area-inset-bottom))] lg:pb-0">
        <OfflineBanner />
        <header className="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-line bg-paper/90 px-4 py-3 backdrop-blur sm:px-6 lg:hidden">
          <Link to="/app" aria-label="Review Responder queue">
            <LogoMark />
          </Link>
          <Button variant="ghost" onClick={signOut}>
            Sign out
          </Button>
        </header>
        {user?.is_demo && (
          <p role="note" className="border-b border-warn/20 bg-warn-tint px-4 py-2 text-center text-xs text-warn sm:px-6">
            Demo account: nothing here is connected to a real Google Business Profile, and nothing is posted to Google.
          </p>
        )}
        <main id="main" className="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:px-8 sm:py-10">
          <Outlet />
        </main>
      </div>

      <nav
        aria-label="Main"
        className="fixed inset-x-0 bottom-0 z-30 flex border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] lg:hidden"
      >
        {nav.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.end}
            className={({ isActive }) =>
              `flex min-w-0 flex-1 flex-col items-center gap-0.5 px-1 py-2 text-[11px] font-medium ${isActive ? 'text-brand' : 'text-muted'}`
            }
          >
            <NavIcon d={item.icon} />
            <span className="truncate">{item.label}</span>
          </NavLink>
        ))}
      </nav>
    </div>
  )
}
