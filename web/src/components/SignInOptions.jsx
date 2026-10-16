import { useState } from 'react'
import { useNavigate } from 'react-router'
import { api, errorMessage } from '../lib/api'
import { startGoogleSignIn, useAuth } from '../lib/auth'
import { useResource } from '../hooks/useResource'
import { Alert, Button, Skeleton } from './ui'

/** "Sign in with Google" and "Try the demo", reflecting what the server has enabled. */
export function SignInOptions() {
  const { signIn } = useAuth()
  const navigate = useNavigate()
  const config = useResource((signal) => api.get('/config', { signal }).then((r) => r.data))
  const [busy, setBusy] = useState(null)
  const [error, setError] = useState(null)

  async function tryDemo() {
    setBusy('demo')
    setError(null)
    try {
      const res = await api.post('/auth/demo')
      signIn(res.data.token, res.data.user)
      navigate('/app')
    } catch (err) {
      setError(errorMessage(err))
      setBusy(null)
    }
  }

  async function google() {
    setBusy('google')
    setError(null)
    try {
      await startGoogleSignIn()
    } catch (err) {
      setError(errorMessage(err))
      setBusy(null)
    }
  }

  if (config.loading) {
    return (
      <div aria-busy="true" className="space-y-3">
        <span className="sr-only" role="status">Checking sign-in options…</span>
        <Skeleton className="h-11" />
        <Skeleton className="h-11" />
      </div>
    )
  }
  if (config.error) {
    return (
      <Alert tone="error" title="Sign-in is unavailable right now">
        <p>{config.error}</p>
        <Button variant="secondary" className="mt-3" onClick={config.reload}>
          Try again
        </Button>
      </Alert>
    )
  }

  const { googleEnabled, demoEnabled } = config.data
  return (
    <div className="space-y-3">
      {error && <Alert tone="error">{error}</Alert>}
      <Button className="w-full min-h-11" onClick={google} busy={busy === 'google'} disabled={!googleEnabled} aria-describedby={googleEnabled ? undefined : 'google-off'}>
        Sign in with Google
      </Button>
      {!googleEnabled && (
        <p id="google-off" className="text-xs text-muted">
          Google sign-in isn&apos;t set up on this server yet (it needs a Google Cloud OAuth client). The demo works without it.
        </p>
      )}
      {demoEnabled && (
        <Button variant="secondary" className="w-full min-h-11" onClick={tryDemo} busy={busy === 'demo'}>
          Try the demo
        </Button>
      )}
      {demoEnabled && <p className="text-xs text-muted">The demo creates a private, throwaway account. It is never connected to a real Google Business Profile.</p>}
    </div>
  )
}
