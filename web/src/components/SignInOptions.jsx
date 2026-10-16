import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
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
  const [agreed, setAgreed] = useState(false)
  const [agreeError, setAgreeError] = useState(null)

  async function tryDemo() {
    if (!agreed) {
      setAgreeError('Please confirm you are 18 or older and accept the Terms to try the demo.')
      document.getElementById('demo-terms')?.focus()
      return
    }
    setBusy('demo')
    setError(null)
    try {
      const res = await api.post('/auth/demo', { accept_terms: true })
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
        {config.slow && (
          <Alert tone="warn">This is taking longer than usual. The server may be waking up, which can take up to a minute — we&apos;re still trying.</Alert>
        )}
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
        <div>
          <div className="flex items-start gap-2.5">
            <input
              id="demo-terms"
              type="checkbox"
              checked={agreed}
              onChange={(e) => {
                setAgreed(e.target.checked)
                if (e.target.checked) setAgreeError(null)
              }}
              aria-invalid={agreeError ? true : undefined}
              aria-describedby={agreeError ? 'demo-terms-error' : undefined}
              className="mt-0.5 size-4 shrink-0 accent-brand"
            />
            <label htmlFor="demo-terms" className="text-sm">
              I am 18 or older and accept the{' '}
              <Link to="/terms" className="font-semibold text-brand underline underline-offset-2">
                Terms of Service
              </Link>
            </label>
          </div>
          {agreeError && (
            <p id="demo-terms-error" className="mt-1 text-xs font-semibold text-danger">
              {agreeError}
            </p>
          )}
        </div>
      )}
      {demoEnabled && (
        <Button variant="secondary" className="w-full min-h-11" onClick={tryDemo} busy={busy === 'demo'}>
          Try the demo
        </Button>
      )}
      {demoEnabled && <p className="text-xs text-muted">The demo creates a private, throwaway account that is deleted after 24 hours. It is never connected to a real Google Business Profile.</p>}
    </div>
  )
}
