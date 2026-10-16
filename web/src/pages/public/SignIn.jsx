import { Navigate, useLocation } from 'react-router'
import { SignInOptions } from '../../components/SignInOptions'
import { Alert } from '../../components/ui'
import { useDocumentTitle } from '../../hooks/useResource'
import { useAuth } from '../../lib/auth'

export default function SignIn() {
  useDocumentTitle('Sign in')
  const { status } = useAuth()
  const location = useLocation()

  if (status === 'authed') return <Navigate to="/app" replace />

  return (
    <div className="mx-auto max-w-sm px-4 py-16">
      <h1 className="text-3xl">Sign in</h1>
      {location.state?.expired && (
        <Alert tone="warn" className="mt-4">
          Your session has expired. Please sign in again.
        </Alert>
      )}
      {location.state?.error && (
        <Alert tone="error" className="mt-4">
          {location.state.error}
        </Alert>
      )}
      <div className="mt-6 rounded-card border border-line bg-surface p-6">
        <SignInOptions />
      </div>
    </div>
  )
}
