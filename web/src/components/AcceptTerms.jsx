import { useState } from 'react'
import { Link } from 'react-router'
import { api, errorMessage } from '../lib/api'
import { useAuth } from '../lib/auth'
import { useDocumentTitle } from '../hooks/useResource'
import { Wordmark } from './Logo'
import { Alert, Button } from './ui'

/** Shown once, before a new Google account can use the app. */
export function AcceptTerms() {
  useDocumentTitle('Before you start')
  const { setUser, signOut } = useAuth()
  const [agreed, setAgreed] = useState(false)
  const [fieldError, setFieldError] = useState(null)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  async function submit(e) {
    e.preventDefault()
    if (!agreed) {
      setFieldError('Please tick the box to continue.')
      document.getElementById('accept-terms')?.focus()
      return
    }
    setBusy(true)
    setError(null)
    try {
      const res = await api.post('/me/accept-terms', { accept_terms: true })
      setUser(res.data.user)
    } catch (err) {
      setError(errorMessage(err))
      setBusy(false)
    }
  }

  return (
    <main id="main" className="mx-auto max-w-lg px-4 py-12">
      <Wordmark />
      <h1 className="mt-8 text-3xl">Before you start</h1>
      <p className="mt-3 text-muted">
        Review Responder drafts and, when you approve them, posts replies to your business&apos;s Google reviews. Please confirm the
        following once.
      </p>
      <form noValidate onSubmit={submit} className="mt-6 rounded-card border border-line bg-surface p-6">
        {error && (
          <Alert tone="error" className="mb-4">
            {error}
          </Alert>
        )}
        <div className="flex items-start gap-3">
          <input
            id="accept-terms"
            type="checkbox"
            checked={agreed}
            onChange={(e) => {
              setAgreed(e.target.checked)
              if (e.target.checked) setFieldError(null)
            }}
            aria-invalid={fieldError ? true : undefined}
            aria-describedby={fieldError ? 'accept-terms-error' : undefined}
            className="mt-1 size-4 shrink-0 accent-brand"
          />
          <label htmlFor="accept-terms" className="text-sm leading-relaxed">
            I am 18 or older, I am authorized to manage this business&apos;s Google Business Profile, and I accept the{' '}
            <Link to="/terms" target="_blank" className="font-semibold text-brand underline underline-offset-2">
              Terms of Service
            </Link>{' '}
            and have read the{' '}
            <Link to="/privacy" target="_blank" className="font-semibold text-brand underline underline-offset-2">
              Privacy Policy
            </Link>
            .
          </label>
        </div>
        {fieldError && (
          <p id="accept-terms-error" className="mt-2 text-sm font-semibold text-danger">
            {fieldError}
          </p>
        )}
        <div className="mt-6 flex flex-wrap gap-3">
          <Button type="submit" busy={busy}>
            Continue
          </Button>
          <Button variant="ghost" onClick={signOut}>
            Sign out
          </Button>
        </div>
      </form>
    </main>
  )
}
