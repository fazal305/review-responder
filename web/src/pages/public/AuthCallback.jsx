import { useEffect, useRef } from 'react'
import { useNavigate } from 'react-router'
import { Skeleton } from '../../components/ui'
import { useDocumentTitle } from '../../hooks/useResource'
import { api, errorMessage } from '../../lib/api'
import { takeGoogleNonce, useAuth } from '../../lib/auth'

const CALLBACK_ERRORS = {
  denied: 'Google sign-in was cancelled. You can try again whenever you like.',
  expired: 'That sign-in attempt expired. Please start again.',
  failed: "We couldn't finish signing you in with Google. Please try again.",
}

export default function AuthCallback() {
  useDocumentTitle('Signing in')
  const { signIn } = useAuth()
  const navigate = useNavigate()
  // The code is single use, so the exchange must not run twice (StrictMode re-runs effects).
  const started = useRef(false)

  useEffect(() => {
    if (started.current) return
    started.current = true

    const params = new URLSearchParams(window.location.hash.slice(1))
    window.history.replaceState(null, '', window.location.pathname)
    const fail = (message) => navigate('/signin', { replace: true, state: { error: message } })

    const code = params.get('code')
    const nonce = takeGoogleNonce()
    if (!code || !nonce) {
      fail(CALLBACK_ERRORS[params.get('error')] || CALLBACK_ERRORS.expired)
      return
    }

    api
      .post('/auth/exchange', { code, nonce })
      .then((res) => {
        signIn(res.data.token, res.data.user)
        navigate('/app', { replace: true })
      })
      .catch((err) => fail(err.response?.status === 422 ? CALLBACK_ERRORS.expired : errorMessage(err)))
  }, [navigate, signIn])

  return (
    <div className="mx-auto max-w-sm space-y-3 px-4 py-16" aria-busy="true">
      <p role="status" className="text-muted">
        Finishing sign-in…
      </p>
      <Skeleton className="h-11" />
    </div>
  )
}
