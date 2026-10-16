/* eslint-disable react/only-export-components -- entry module, never hot-reloaded as a component */
import { StrictMode, Suspense, lazy } from 'react'
import { createRoot } from 'react-dom/client'
import { createBrowserRouter, Outlet, RouterProvider, ScrollRestoration } from 'react-router'
import './index.css'
import { AuthProvider } from './lib/auth'
import PublicLayout from './layouts/PublicLayout'
import AppLayout, { RequireAdmin, RequireAuth } from './layouts/AppLayout'
import { Skeleton } from './components/ui'
import NotFound from './pages/public/NotFound'

const Landing = lazy(() => import('./pages/public/Landing'))
const SignIn = lazy(() => import('./pages/public/SignIn'))
const AuthCallback = lazy(() => import('./pages/public/AuthCallback'))
const Queue = lazy(() => import('./pages/app/Queue'))
const Locations = lazy(() => import('./pages/app/Locations'))
const Settings = lazy(() => import('./pages/app/Settings'))
const Admin = lazy(() => import('./pages/app/Admin'))

function Root() {
  return (
    <AuthProvider>
      <ScrollRestoration />
      <Outlet />
    </AuthProvider>
  )
}

function Page({ children }) {
  return (
    <Suspense
      fallback={
        <div aria-busy="true" className="mx-auto max-w-5xl space-y-3 p-6">
          <Skeleton className="h-10 w-56" />
          <Skeleton className="h-40" />
        </div>
      }
    >
      {children}
    </Suspense>
  )
}

const router = createBrowserRouter([
  {
    element: <Root />,
    children: [
      {
        element: <PublicLayout />,
        children: [
          { path: '/', element: <Page><Landing /></Page> },
          { path: '/signin', element: <Page><SignIn /></Page> },
          { path: '/auth/callback', element: <Page><AuthCallback /></Page> },
          { path: '*', element: <NotFound /> },
        ],
      },
      {
        path: '/app',
        element: (
          <RequireAuth>
            <AppLayout />
          </RequireAuth>
        ),
        children: [
          { index: true, element: <Page><Queue /></Page> },
          { path: 'locations', element: <Page><Locations /></Page> },
          { path: 'settings', element: <Page><Settings /></Page> },
          { path: 'admin', element: <RequireAdmin><Page><Admin /></Page></RequireAdmin> },
          { path: '*', element: <NotFound inApp /> },
        ],
      },
    ],
  },
])

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <RouterProvider router={router} />
  </StrictMode>,
)
