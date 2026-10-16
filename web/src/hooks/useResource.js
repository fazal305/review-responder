import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react'
import { errorMessage } from '../lib/api'

const SLOW_AFTER_MS = 4000

/**
 * Loads data with loading, slow-network and error states.
 * `fetcher` receives an AbortSignal and must return a promise of the data.
 */
export function useResource(fetcher) {
  const [state, setState] = useState({ data: null, error: null, loading: true, slow: false })
  const [nonce, setNonce] = useState(0)
  const fetcherRef = useRef(fetcher)
  useLayoutEffect(() => {
    fetcherRef.current = fetcher
  })

  useEffect(() => {
    const controller = new AbortController()
    const timer = setTimeout(() => setState((s) => (s.loading ? { ...s, slow: true } : s)), SLOW_AFTER_MS)

    fetcherRef
      .current(controller.signal)
      .then((data) => {
        if (!controller.signal.aborted) setState({ data, error: null, loading: false, slow: false })
      })
      .catch((err) => {
        if (controller.signal.aborted) return
        setState((s) => ({ ...s, error: errorMessage(err), loading: false, slow: false }))
      })
      .finally(() => clearTimeout(timer))

    return () => {
      controller.abort()
      clearTimeout(timer)
    }
  }, [nonce])

  const reload = useCallback(() => {
    setState((s) => ({ ...s, loading: true, slow: false, error: null }))
    setNonce((n) => n + 1)
  }, [])

  return { ...state, reload }
}

export function useOnline() {
  const [online, setOnline] = useState(() => (typeof navigator === 'undefined' ? true : navigator.onLine))
  useEffect(() => {
    const up = () => setOnline(true)
    const down = () => setOnline(false)
    window.addEventListener('online', up)
    window.addEventListener('offline', down)
    return () => {
      window.removeEventListener('online', up)
      window.removeEventListener('offline', down)
    }
  }, [])
  return online
}

export function useDocumentTitle(title) {
  useEffect(() => {
    document.title = title ? `${title} · Review Responder` : 'Review Responder'
  }, [title])
}
