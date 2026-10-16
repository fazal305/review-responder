import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Alert, Button, Card, ConfirmDialog, PageHeader } from '../../components/ui'
import { useDocumentTitle } from '../../hooks/useResource'
import { api, errorMessage } from '../../lib/api'
import { useAuth } from '../../lib/auth'

export default function Settings() {
  useDocumentTitle('Settings')
  const { user, forget } = useAuth()
  const navigate = useNavigate()
  const [confirming, setConfirming] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  async function deleteAccount() {
    setBusy(true)
    setError(null)
    try {
      await api.delete('/me')
      forget()
      navigate('/', { replace: true })
    } catch (err) {
      setError(errorMessage(err))
      setBusy(false)
      setConfirming(false)
    }
  }

  return (
    <>
      <PageHeader title="Settings" />
      <div className="space-y-6">
        <Card>
          <h2 className="text-xl">Account</h2>
          <dl className="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[10rem_minmax(0,1fr)]">
            <dt className="text-muted">Name</dt>
            <dd>{user.name}</dd>
            <dt className="text-muted">Email</dt>
            <dd>{user.is_demo ? 'Demo account (no email)' : user.email}</dd>
            <dt className="text-muted">Google</dt>
            <dd>{user.google_connected ? 'Connected' : 'Not connected'}</dd>
            <dt className="text-muted">Timezone</dt>
            <dd>{user.timezone}</dd>
          </dl>
        </Card>

        <Card>
          <h2 className="text-xl">Reply guidelines and morning email</h2>
          <p className="mt-2 text-sm text-muted">Tone, sign-off, phrases to avoid and your daily summary email will be set here.</p>
        </Card>

        <Card className="border-danger/30">
          <h2 className="text-xl">Delete account</h2>
          <p className="mt-2 text-sm text-muted">
            Permanently removes your account, locations, reviews and drafts from Review Responder and revokes its access to your Google
            account. Replies already posted to Google stay there.
          </p>
          {error && (
            <Alert tone="error" className="mt-4">
              {error}
            </Alert>
          )}
          <Button variant="danger" className="mt-4" onClick={() => setConfirming(true)}>
            Delete my account
          </Button>
        </Card>
      </div>

      <ConfirmDialog
        open={confirming}
        title="Delete your account?"
        confirmLabel="Delete account"
        busy={busy}
        onConfirm={deleteAccount}
        onCancel={() => setConfirming(false)}
      >
        This can&apos;t be undone.
      </ConfirmDialog>
    </>
  )
}
