import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { api, getToken, setToken } from './api'

const AuthContext = createContext(null)
const NONCE_KEY = 'rr_oauth_nonce'

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [status, setStatus] = useState(() => (getToken() ? 'checking' : 'guest'))
  const [expired, setExpired] = useState(false)

  useEffect(() => {
    if (status !== 'checking') return
    let active = true
    api
      .get('/me')
      .then((res) => {
        if (!active) return
        setUser(res.data.user)
        setStatus('authed')
      })
      .catch((err) => {
        if (!active) return
        if (err.response?.status === 401) setStatus('guest')
        else setStatus('unreachable')
      })
    return () => {
      active = false
    }
  }, [status])

  useEffect(() => {
    const onExpired = () => {
      setUser(null)
      setExpired(true)
      setStatus('guest')
    }
    window.addEventListener('session-expired', onExpired)
    return () => window.removeEventListener('session-expired', onExpired)
  }, [])

  const signIn = useCallback((token, nextUser) => {
    setToken(token)
    setUser(nextUser)
    setExpired(false)
    setStatus('authed')
  }, [])

  const signOut = useCallback(async () => {
    try {
      await api.post('/auth/logout')
    } catch {
      // token may already be invalid; local sign-out still applies
    }
    setToken(null)
    setUser(null)
    setStatus('guest')
  }, [])

  /** Local sign-out after the server has already removed the account. */
  const forget = useCallback(() => {
    setToken(null)
    setUser(null)
    setStatus('guest')
  }, [])

  const retry = useCallback(() => setStatus('checking'), [])

  const value = useMemo(
    () => ({ user, status, expired, signIn, signOut, forget, retry, setUser }),
    [user, status, expired, signIn, signOut, forget, retry],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react/only-export-components
export function useAuth() {
  return useContext(AuthContext)
}

/** Starts Google sign-in: the nonce stays in this tab and is required to redeem the callback code. */
// eslint-disable-next-line react/only-export-components
export async function startGoogleSignIn() {
  const bytes = crypto.getRandomValues(new Uint8Array(32))
  const nonce = btoa(String.fromCharCode(...bytes)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
  sessionStorage.setItem(NONCE_KEY, nonce)
  const res = await api.get('/auth/google/redirect', { params: { nonce } })
  window.location.assign(res.data.url)
}

// eslint-disable-next-line react/only-export-components
export function takeGoogleNonce() {
  try {
    const nonce = sessionStorage.getItem(NONCE_KEY)
    sessionStorage.removeItem(NONCE_KEY)
    return nonce
  } catch {
    return null
  }
}
