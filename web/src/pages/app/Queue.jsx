import { useCallback, useId, useRef, useState } from 'react'
import { Alert, Button, ConfirmDialog, EmptyState, LinkButton, LoadState, PageHeader, Toast } from '../../components/ui'
import { useDocumentTitle, useResource } from '../../hooks/useResource'
import { api, errorMessage } from '../../lib/api'

const MAX_REPLY = 4000

const TONES = [
  ['more_apologetic', 'More apologetic'],
  ['more_casual', 'More casual'],
  ['more_formal', 'More formal'],
  ['shorter', 'Shorter'],
]

const SENTIMENT_STYLES = {
  positive: 'bg-success-tint text-success',
  neutral: 'bg-sunken text-muted',
  negative: 'bg-danger-tint text-danger',
}

/** Uses the server's own message for conflicts and Google failures, which are written for the owner. */
function actionError(err, fallback) {
  const { status, data } = err?.response ?? {}
  if ((status === 409 || status === 502) && data?.message) return data.message
  return errorMessage(err, fallback)
}

export default function Queue() {
  useDocumentTitle('Queue')
  const resource = useResource((signal) => api.get('/queue', { signal }).then((res) => res.data.data))
  const [groups, setGroups] = useState(null)
  const [posted, setPosted] = useState(0)
  const [toast, setToast] = useState(null)
  const clearToast = useCallback(() => setToast(null), [])
  const [dismissing, setDismissing] = useState(null)
  const [dismissBusy, setDismissBusy] = useState(false)
  const [dismissError, setDismissError] = useState(null)
  const summaryRef = useRef(null)

  const list = groups ?? resource.data ?? []
  const waiting = list.reduce((sum, group) => sum + group.drafts.length, 0)

  function replaceDraft(updated) {
    setGroups((current) =>
      (current ?? resource.data).map((group) => ({
        ...group,
        drafts: group.drafts.map((d) => (d.id === updated.id ? { ...d, ...updated, review: updated.review ?? d.review } : d)),
      })),
    )
  }

  function removeDraft(id, message) {
    setGroups((current) =>
      (current ?? resource.data)
        .map((group) => ({ ...group, drafts: group.drafts.filter((d) => d.id !== id) }))
        .filter((group) => group.drafts.length > 0),
    )
    setToast(message)
    // The focused card is gone, so keep keyboard users in the queue rather than at the top of the page.
    summaryRef.current?.focus()
  }

  function onPosted(id) {
    setPosted((n) => n + 1)
    removeDraft(id, 'Reply posted.')
  }

  async function confirmDismiss() {
    setDismissBusy(true)
    setDismissError(null)
    try {
      await api.post(`/drafts/${dismissing.id}/dismiss`)
      setDismissing(null)
      removeDraft(dismissing.id, 'Draft dismissed.')
    } catch (err) {
      setDismissError(actionError(err, "We couldn't dismiss that draft. Please try again."))
    } finally {
      setDismissBusy(false)
    }
  }

  return (
    <>
      <PageHeader title="This morning's queue" description="Reply drafts waiting for your review, grouped by location." />

      <LoadState resource={resource} label="Loading your queue">
        {() => (
          <>
            <p ref={summaryRef} tabIndex={-1} className="mb-6 text-sm text-muted focus-visible:outline-none">
              {waiting > 0 ? `${waiting} ${waiting === 1 ? 'draft' : 'drafts'} waiting` : 'Nothing waiting'}
              {posted > 0 && ` · ${posted} posted this session`}
            </p>
            {list.length === 0 ? (
              <EmptyState title="Inbox zero" action={<LinkButton variant="secondary" to="/app/locations">Go to locations</LinkButton>}>
                No drafts are waiting. Drafts appear here after Review Responder checks a monitored location for new reviews.
              </EmptyState>
            ) : (
              <div className="space-y-10">
                {list.map((group) => (
                  <section key={group.id} aria-labelledby={`loc-${group.id}`}>
                    <div className="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1">
                      <h2 id={`loc-${group.id}`} className="text-2xl">
                        {group.title}
                      </h2>
                      <span className="text-sm text-muted">
                        {group.drafts.length} {group.drafts.length === 1 ? 'draft' : 'drafts'}
                      </span>
                      {group.is_sample && (
                        <span className="rounded-full bg-warn-tint px-2 py-0.5 text-xs font-semibold text-warn">Sample data</span>
                      )}
                    </div>
                    <ul className="space-y-4">
                      {group.drafts.map((draft) => (
                        <DraftCard
                          key={draft.id}
                          draft={draft}
                          onChange={replaceDraft}
                          onPosted={onPosted}
                          onDismiss={() => {
                            setDismissError(null)
                            setDismissing(draft)
                          }}
                          onToast={setToast}
                        />
                      ))}
                    </ul>
                  </section>
                ))}
              </div>
            )}
          </>
        )}
      </LoadState>

      <ConfirmDialog
        open={dismissing !== null}
        title="Dismiss this draft?"
        confirmLabel="Dismiss draft"
        busy={dismissBusy}
        onConfirm={confirmDismiss}
        onCancel={() => !dismissBusy && setDismissing(null)}
      >
        <p>
          The draft for {dismissing?.review.reviewer_display_name}&apos;s review leaves your queue and nothing is posted to Google. This
          can&apos;t be undone.
        </p>
        {dismissError && (
          <Alert tone="error" className="mt-3">
            {dismissError}
          </Alert>
        )}
      </ConfirmDialog>

      <Toast message={toast} onDone={clearToast} />
    </>
  )
}

function DraftCard({ draft, onChange, onPosted, onDismiss, onToast }) {
  const id = useId()
  const textareaRef = useRef(null)
  const toneButtonRef = useRef(null)
  const [text, setText] = useState(draft.body)
  const [writing, setWriting] = useState(false)
  const [busy, setBusy] = useState(null)
  const [fieldError, setFieldError] = useState(null)
  const [error, setError] = useState(null)
  const [tonesOpen, setTonesOpen] = useState(false)

  const failed = draft.status === 'draft_failed'
  const showEditor = !failed || writing || draft.body !== ''
  const dirty = text.trim() !== draft.body
  const review = draft.review

  function validate() {
    const message = text.trim() === '' ? 'Write a reply before posting.' : text.length > MAX_REPLY ? `Replies can be at most ${MAX_REPLY} characters.` : null
    setFieldError(message)
    if (message) textareaRef.current?.focus()
    return !message
  }

  async function run(action, request, fallback) {
    setBusy(action)
    setError(null)
    try {
      return await request()
    } catch (err) {
      const fieldMessage = err?.response?.status === 422 ? err.response.data?.errors?.body?.[0] : null
      if (fieldMessage) {
        setFieldError(fieldMessage)
        textareaRef.current?.focus()
      } else {
        setError(actionError(err, fallback))
      }
      return null
    } finally {
      setBusy(null)
    }
  }

  async function save() {
    if (!validate()) return
    const res = await run('save', () => api.patch(`/drafts/${draft.id}`, { body: text }), "We couldn't save your edit. Please try again.")
    if (res) {
      onChange(res.data.data)
      setText(res.data.data.body)
      onToast('Edit saved.')
    }
  }

  async function approve() {
    if (!validate()) return
    const res = await run('approve', () => api.post(`/drafts/${draft.id}/approve`, { body: text }), "We couldn't post your reply. Please try again.")
    if (res) onPosted(draft.id)
  }

  async function regenerate(tone) {
    setTonesOpen(false)
    setFieldError(null)
    const res = await run('regenerate', () => api.post(`/drafts/${draft.id}/regenerate`, tone ? { tone } : {}), "We couldn't create a new draft. Please try again.")
    if (res) {
      const updated = res.data.data
      onChange(updated)
      setText(updated.body)
      if (updated.status === 'pending_review') onToast('New draft ready.')
    }
  }

  function startWriting() {
    setWriting(true)
    // The textarea mounts on this render, so focus it on the next frame.
    requestAnimationFrame(() => textareaRef.current?.focus())
  }

  const locked = busy !== null

  return (
    <li className="rounded-card border border-line bg-surface p-5">
      <article aria-labelledby={`${id}-reviewer`} className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <Stars rating={review.star_rating} />
            <span className="text-xs text-muted">{review.star_rating} out of 5 stars</span>
          </div>
          <h3 id={`${id}-reviewer`} className="mt-2 text-lg">
            {review.reviewer_display_name}
          </h3>
          <p className="text-xs text-muted">
            <time dateTime={review.reviewed_at} title={new Date(review.reviewed_at).toLocaleString()}>
              {relativeDate(review.reviewed_at)}
            </time>
          </p>
          {(review.sentiment || review.themes.length > 0) && (
            <ul className="mt-3 flex flex-wrap gap-1.5" aria-label="Sentiment and themes">
              {review.sentiment && (
                <li className={`rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${SENTIMENT_STYLES[review.sentiment]}`}>{review.sentiment}</li>
              )}
              {review.themes.map((theme) => (
                <li key={theme} className="rounded-full border border-line px-2 py-0.5 text-xs text-muted [overflow-wrap:anywhere]">
                  {theme}
                </li>
              ))}
            </ul>
          )}
          {review.comment ? (
            <blockquote className="mt-3 whitespace-pre-line border-l-2 border-line-strong pl-3 text-sm leading-relaxed [overflow-wrap:anywhere]">
              {review.comment}
            </blockquote>
          ) : (
            <p className="mt-3 text-sm italic text-muted">No written comment</p>
          )}
        </div>

        <div className="min-w-0">
          {failed && (
            <Alert tone="error" className="mb-3">
              {draft.error}
            </Alert>
          )}

          {showEditor && (
            <>
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <label htmlFor={`${id}-reply`} className="text-sm font-semibold">
                  Your reply
                </label>
                {dirty && <span className="text-xs font-semibold text-warn">Unsaved changes</span>}
              </div>
              <textarea
                ref={textareaRef}
                id={`${id}-reply`}
                value={text}
                rows={6}
                disabled={busy === 'approve' || busy === 'regenerate'}
                aria-invalid={fieldError ? true : undefined}
                aria-describedby={`${id}-count${fieldError ? ` ${id}-error` : ''}`}
                onChange={(e) => {
                  setText(e.target.value)
                  if (fieldError) setFieldError(null)
                }}
                className={`mt-1.5 block w-full resize-y rounded-lg border bg-surface px-3 py-2 text-sm leading-relaxed disabled:opacity-60 ${fieldError ? 'border-danger' : 'border-line-strong'}`}
              />
              <div className="mt-1 flex flex-wrap justify-between gap-x-4 gap-y-1 text-xs">
                {fieldError ? (
                  <p id={`${id}-error`} className="font-semibold text-danger">
                    {fieldError}
                  </p>
                ) : (
                  <GeneratorNote draft={draft} />
                )}
                <p id={`${id}-count`} className={`ml-auto ${text.length > MAX_REPLY ? 'font-semibold text-danger' : 'text-muted'}`}>
                  {text.length} / {MAX_REPLY} characters
                </p>
              </div>
            </>
          )}

          {error && (
            <Alert tone="error" className="mt-3">
              {error}
            </Alert>
          )}

          <div className="relative mt-4 flex flex-wrap gap-2">
            {showEditor && (
              <Button busy={busy === 'approve'} disabled={locked && busy !== 'approve'} onClick={approve}>
                {busy === 'approve' ? 'Posting…' : 'Approve & Post'}
              </Button>
            )}
            {showEditor && dirty && (
              <Button variant="secondary" busy={busy === 'save'} disabled={locked && busy !== 'save'} onClick={save}>
                {busy === 'save' ? 'Saving…' : 'Save edit'}
              </Button>
            )}
            {failed && (
              <Button variant={showEditor ? 'secondary' : 'primary'} busy={busy === 'regenerate'} disabled={locked && busy !== 'regenerate'} onClick={() => regenerate(null)}>
                {busy === 'regenerate' ? 'Retrying…' : 'Retry generation'}
              </Button>
            )}
            {failed && !showEditor && (
              <Button variant="secondary" disabled={locked} onClick={startWriting}>
                Write it yourself
              </Button>
            )}
            {!failed && (
              // On phones the tone list spans the whole action row; from sm up it sits under the button.
              <div
                className="sm:relative"
                onBlur={(e) => {
                  if (!e.currentTarget.contains(e.relatedTarget)) setTonesOpen(false)
                }}
                onKeyDown={(e) => {
                  if (e.key === 'Escape' && tonesOpen) {
                    setTonesOpen(false)
                    toneButtonRef.current?.focus()
                  }
                }}
              >
                <Button
                  ref={toneButtonRef}
                  variant="secondary"
                  busy={busy === 'regenerate'}
                  disabled={locked && busy !== 'regenerate'}
                  aria-expanded={tonesOpen}
                  aria-controls={`${id}-tones`}
                  onClick={() => setTonesOpen((open) => !open)}
                >
                  {busy === 'regenerate' ? 'Regenerating…' : 'Regenerate'}
                </Button>
                {tonesOpen && (
                  <div
                    id={`${id}-tones`}
                    role="group"
                    aria-label="Regenerate with a tone"
                    className="absolute inset-x-0 top-full z-10 mt-2 rounded-lg sm:inset-x-auto sm:left-0 sm:w-56 border border-line bg-surface p-1.5 shadow-lg"
                  >
                    {dirty && <p className="px-2.5 pb-1.5 pt-1 text-xs text-warn">Replaces your unsaved edits.</p>}
                    {TONES.map(([value, label]) => (
                      <button
                        key={value}
                        type="button"
                        onClick={() => regenerate(value)}
                        className="block w-full rounded-md px-2.5 py-2 text-left text-sm hover:bg-sunken"
                      >
                        {label}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}
            <Button variant="ghost" disabled={locked} onClick={onDismiss} className="sm:ml-auto">
              Dismiss
            </Button>
          </div>
        </div>
      </article>
    </li>
  )
}

function GeneratorNote({ draft }) {
  const tone = TONES.find(([value]) => value === draft.tone_modifier)?.[1]
  let note = null
  if (draft.generator === 'manual') note = 'Edited by you'
  else if (draft.generator === 'template') note = 'Template draft (no AI key configured)'
  else if (draft.status === 'pending_review') note = `AI draft (${draft.generator})`
  if (note && tone && draft.generator !== 'manual') note += ` · ${tone}`
  return <p className="text-muted">{note}</p>
}

function Stars({ rating }) {
  return (
    <span aria-hidden="true" className="flex gap-0.5">
      {[1, 2, 3, 4, 5].map((n) => (
        <svg key={n} viewBox="0 0 20 20" className={`size-4 ${n <= rating ? 'text-accent' : 'text-line-strong'}`} fill="currentColor">
          <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9l-5.2 2.7 1-5.8L1.5 7.7l5.9-.9z" />
        </svg>
      ))}
    </span>
  )
}

const relativeFormat = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })
const UNITS = [
  ['year', 31536000],
  ['month', 2592000],
  ['week', 604800],
  ['day', 86400],
  ['hour', 3600],
  ['minute', 60],
]

function relativeDate(iso) {
  const seconds = (new Date(iso).getTime() - Date.now()) / 1000
  for (const [unit, size] of UNITS) {
    if (Math.abs(seconds) >= size) return relativeFormat.format(Math.round(seconds / size), unit)
  }
  return 'just now'
}
