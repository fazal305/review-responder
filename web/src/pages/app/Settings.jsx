import { useCallback, useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { Alert, Button, Card, ConfirmDialog, LoadState, PageHeader, Toast } from '../../components/ui'
import { useDocumentTitle, useResource } from '../../hooks/useResource'
import { api, errorMessage } from '../../lib/api'
import { startGoogleSignIn, useAuth } from '../../lib/auth'

const TONES = [
  { value: 'friendly', label: 'Friendly' },
  { value: 'professional', label: 'Professional' },
  { value: 'formal', label: 'Formal' },
  { value: 'casual', label: 'Casual' },
]

const STRICTNESS = [
  { value: 'relaxed', label: 'Relaxed — warm and personal, may mention details from the review' },
  { value: 'balanced', label: 'Balanced — mentions details, never promises refunds or discounts' },
  { value: 'strict', label: 'Strict — brief and neutral, no promises, no admissions of fault' },
]

const LIMITS = { sign_off: 120, banned_phrases: 1000, extra_instructions: 1000 }

const HOURS = Array.from({ length: 24 }, (_, h) => ({ value: h, label: `${String(h).padStart(2, '0')}:00` }))

const TIMEZONES = (() => {
  try {
    return Intl.supportedValuesOf('timeZone')
  } catch {
    return []
  }
})()

const inputClass = (invalid) =>
  `mt-1.5 block w-full rounded-lg border bg-surface px-3 text-sm disabled:opacity-60 ${invalid ? 'border-danger' : 'border-line-strong'}`

export default function Settings() {
  useDocumentTitle('Settings')
  const { user } = useAuth()
  const settings = useResource((signal) => api.get('/settings', { signal }).then((r) => r.data))
  const [toast, setToast] = useState(null)
  const clearToast = useCallback(() => setToast(null), [])

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
            <dd className="[overflow-wrap:anywhere]">{user.is_demo ? 'Demo account (no email)' : user.email}</dd>
          </dl>
        </Card>

        <GoogleConnection user={user} />

        <LoadState resource={settings} label="Loading settings">
          {(data) => <SettingsForm initial={data} onSaved={() => setToast('Settings saved.')} />}
        </LoadState>

        <DeleteAccount />

        <p className="text-sm text-muted">
          <Link to="/privacy" className="underline underline-offset-2 hover:text-ink">
            Privacy Policy
          </Link>
          {' · '}
          <Link to="/terms" className="underline underline-offset-2 hover:text-ink">
            Terms of Service
          </Link>
          {' · '}
          <Link to="/faq" className="underline underline-offset-2 hover:text-ink">
            FAQ
          </Link>
        </p>
      </div>
      <Toast message={toast} onDone={clearToast} />
    </>
  )
}

function GoogleConnection({ user }) {
  const config = useResource((signal) => api.get('/config', { signal }).then((r) => r.data))
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  async function connect() {
    setBusy(true)
    setError(null)
    try {
      await startGoogleSignIn()
    } catch (err) {
      setError(errorMessage(err))
      setBusy(false)
    }
  }

  const googleEnabled = config.data?.googleEnabled
  return (
    <Card>
      <h2 className="text-xl">Google connection</h2>
      {user.is_demo ? (
        <>
          <p className="mt-3 flex flex-wrap items-center gap-2 text-sm">
            <span className="rounded-full bg-warn-tint px-2 py-0.5 text-xs font-semibold text-warn">Sample data</span>
            Demo account — not connected to any Google Business Profile.
          </p>
          {googleEnabled && <p className="mt-2 text-sm text-muted">To work with your real business, sign in with Google. That creates a separate account.</p>}
        </>
      ) : user.google_connected ? (
        <p className="mt-3 text-sm">
          Connected as <span className="font-semibold [overflow-wrap:anywhere]">{user.email}</span>. Review Responder can read reviews for your
          Business Profile locations and posts only the replies you approve.
        </p>
      ) : (
        <p className="mt-3 text-sm">Not connected. Sign in with Google to give Review Responder access to your Business Profile.</p>
      )}
      {error && (
        <Alert tone="error" className="mt-4">
          {error}
        </Alert>
      )}
      {googleEnabled && (user.is_demo || !user.google_connected) && (
        <Button className="mt-4" busy={busy} onClick={connect}>
          {user.is_demo ? 'Sign in with Google' : 'Connect Google'}
        </Button>
      )}
    </Card>
  )
}

function validate(values) {
  const errors = {}
  for (const [field, max] of Object.entries(LIMITS)) {
    if ((values[field] || '').length > max) errors[field] = `Keep this to ${max.toLocaleString()} characters or fewer.`
  }
  if (!Number.isInteger(values.notify_hour) || values.notify_hour < 0 || values.notify_hour > 23) errors.notify_hour = 'Choose an hour between 0 and 23.'
  if (!values.timezone) errors.timezone = 'Choose a timezone from the list.'
  return errors
}

function SettingsForm({ initial, onSaved }) {
  const { user, setUser } = useAuth()
  const [values, setValues] = useState(() => ({
    ...initial.guidelines,
    sign_off: initial.guidelines.sign_off ?? '',
    banned_phrases: initial.guidelines.banned_phrases ?? '',
    extra_instructions: initial.guidelines.extra_instructions ?? '',
    ...initial.notifications,
  }))
  const [errors, setErrors] = useState({})
  const [error, setError] = useState(null)
  const [saving, setSaving] = useState(false)
  const zones = TIMEZONES.includes(values.timezone) ? TIMEZONES : [values.timezone, ...TIMEZONES]

  function set(field, value) {
    setValues((v) => ({ ...v, [field]: value }))
    if (errors[field]) setErrors(({ [field]: _removed, ...rest }) => rest)
  }

  async function submit(e) {
    e.preventDefault()
    setError(null)
    const found = validate(values)
    setErrors(found)
    if (Object.keys(found).length) {
      document.getElementById(`settings-${Object.keys(found)[0]}`)?.focus()
      return
    }
    setSaving(true)
    try {
      const res = await api.put('/settings', values)
      setUser(res.data.user)
      onSaved()
    } catch (err) {
      const fieldErrors = err.response?.status === 422 ? err.response.data.errors : null
      if (fieldErrors) setErrors(Object.fromEntries(Object.entries(fieldErrors).map(([k, v]) => [k, v[0]])))
      setError(errorMessage(err, "We couldn't save your settings. Please try again."))
    } finally {
      setSaving(false)
    }
  }

  const field = (name) => ({
    id: `settings-${name}`,
    disabled: saving,
    'aria-invalid': errors[name] ? true : undefined,
    'aria-describedby': [`settings-${name}-hint`, errors[name] && `settings-${name}-error`].filter(Boolean).join(' '),
  })
  const fieldError = (name) =>
    errors[name] && (
      <p id={`settings-${name}-error`} className="mt-1 text-xs font-semibold text-danger">
        {errors[name]}
      </p>
    )
  const counter = (name, hint) => (
    <p id={`settings-${name}-hint`} className="mt-1 flex flex-wrap justify-between gap-x-4 text-xs text-muted">
      <span>{hint}</span>
      <span className={values[name].length > LIMITS[name] ? 'font-semibold text-danger' : ''}>
        {values[name].length} / {LIMITS[name].toLocaleString()}
      </span>
    </p>
  )

  return (
    <form noValidate onSubmit={submit} className="space-y-6" aria-label="Reply guidelines and morning email">
      <Card>
        <h2 className="text-xl">Reply guidelines</h2>
        <p className="mt-1 text-sm text-muted">Used for every new and regenerated draft.</p>
        <div className="mt-5 grid gap-5 sm:grid-cols-2">
          <div>
            <label htmlFor="settings-tone" className="text-sm font-semibold">
              Tone
            </label>
            <select {...field('tone')} value={values.tone} onChange={(e) => set('tone', e.target.value)} className={`${inputClass(errors.tone)} min-h-10`}>
              {TONES.map((t) => (
                <option key={t.value} value={t.value}>
                  {t.label}
                </option>
              ))}
            </select>
            <p id="settings-tone-hint" className="sr-only">
              How replies should sound.
            </p>
            {fieldError('tone')}
          </div>
          <div>
            <label htmlFor="settings-sign_off" className="text-sm font-semibold">
              Sign-off
            </label>
            <input
              {...field('sign_off')}
              value={values.sign_off}
              onChange={(e) => set('sign_off', e.target.value)}
              placeholder="e.g. — Maria and the team"
              className={`${inputClass(errors.sign_off)} min-h-10`}
            />
            {counter('sign_off', 'Added at the end of each reply.')}
            {fieldError('sign_off')}
          </div>
        </div>

        <fieldset className="mt-5" aria-describedby={errors.strictness ? 'settings-strictness-error' : undefined}>
          <legend className="text-sm font-semibold">How careful should replies be?</legend>
          <div className="mt-2 space-y-2">
            {STRICTNESS.map((s) => (
              <label key={s.value} className="flex items-start gap-2.5 text-sm">
                <input
                  type="radio"
                  name="strictness"
                  value={s.value}
                  checked={values.strictness === s.value}
                  disabled={saving}
                  onChange={() => set('strictness', s.value)}
                  className="mt-0.5 size-4 shrink-0 accent-brand"
                />
                {s.label}
              </label>
            ))}
          </div>
          {fieldError('strictness')}
        </fieldset>

        <div className="mt-5">
          <label htmlFor="settings-banned_phrases" className="text-sm font-semibold">
            Phrases to avoid
          </label>
          <textarea
            {...field('banned_phrases')}
            rows={3}
            value={values.banned_phrases}
            onChange={(e) => set('banned_phrases', e.target.value)}
            placeholder={'valued customer\nsorry for any inconvenience'}
            className={`${inputClass(errors.banned_phrases)} resize-y py-2`}
          />
          {counter('banned_phrases', 'One phrase per line.')}
          {fieldError('banned_phrases')}
        </div>

        <div className="mt-5">
          <label htmlFor="settings-extra_instructions" className="text-sm font-semibold">
            Notes for the drafter
          </label>
          <textarea
            {...field('extra_instructions')}
            rows={3}
            value={values.extra_instructions}
            onChange={(e) => set('extra_instructions', e.target.value)}
            placeholder="e.g. We are closed on Mondays. Invite unhappy customers to email hello@example.com."
            className={`${inputClass(errors.extra_instructions)} resize-y py-2`}
          />
          {counter('extra_instructions', 'Facts or preferences to keep in mind. Template drafts use only the sign-off.')}
          {fieldError('extra_instructions')}
        </div>
      </Card>

      <Card>
        <h2 className="text-xl">Morning email</h2>
        <p className="mt-1 text-sm text-muted">
          A short summary of new drafts per location, sent once a day at the hour you choose — only when there is something new.
        </p>
        {user.is_demo && (
          <Alert tone="warn" className="mt-4">
            Demo accounts have no real email address, so no summary is ever sent. You can still try the settings.
          </Alert>
        )}
        <label className="mt-5 flex items-center gap-2.5 text-sm font-semibold">
          <input
            id="settings-notify_daily"
            type="checkbox"
            checked={values.notify_daily}
            disabled={saving}
            onChange={(e) => set('notify_daily', e.target.checked)}
            className="size-4 accent-brand"
          />
          Send me the daily summary email
        </label>
        <div className="mt-5 grid gap-5 sm:grid-cols-2">
          <div>
            <label htmlFor="settings-notify_hour" className="text-sm font-semibold">
              Send at
            </label>
            <select
              {...field('notify_hour')}
              value={values.notify_hour}
              onChange={(e) => set('notify_hour', Number(e.target.value))}
              className={`${inputClass(errors.notify_hour)} min-h-10`}
            >
              {HOURS.map((h) => (
                <option key={h.value} value={h.value}>
                  {h.label}
                </option>
              ))}
            </select>
            <p id="settings-notify_hour-hint" className="mt-1 text-xs text-muted">
              In your timezone.
            </p>
            {fieldError('notify_hour')}
          </div>
          <div>
            <label htmlFor="settings-timezone" className="text-sm font-semibold">
              Your timezone
            </label>
            <select {...field('timezone')} value={values.timezone} onChange={(e) => set('timezone', e.target.value)} className={`${inputClass(errors.timezone)} min-h-10`}>
              {zones.map((zone) => (
                <option key={zone} value={zone}>
                  {zone}
                </option>
              ))}
            </select>
            <p id="settings-timezone-hint" className="mt-1 text-xs text-muted">
              Each location keeps its own timezone for the nightly check.
            </p>
            {fieldError('timezone')}
          </div>
        </div>
      </Card>

      {error && <Alert tone="error">{error}</Alert>}
      <Button type="submit" busy={saving}>
        {saving ? 'Saving…' : 'Save settings'}
      </Button>
    </form>
  )
}

function DeleteAccount() {
  const { forget } = useAuth()
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
    <Card className="border-danger/30">
      <h2 className="text-xl">Delete account</h2>
      <p className="mt-2 text-sm text-muted">
        Permanently removes your account, locations, reviews and drafts from Review Responder and revokes its access to your Google account.
        Replies already posted to Google stay there.
      </p>
      {error && (
        <Alert tone="error" className="mt-4">
          {error}
        </Alert>
      )}
      <Button variant="danger" className="mt-4" onClick={() => setConfirming(true)}>
        Delete my account
      </Button>
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
    </Card>
  )
}
