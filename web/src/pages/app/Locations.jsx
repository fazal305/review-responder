import { useCallback, useState } from 'react'
import { Link } from 'react-router'
import { Alert, Button, EmptyState, LoadState, PageHeader, Toast } from '../../components/ui'
import { useDocumentTitle, useResource } from '../../hooks/useResource'
import { api, errorMessage } from '../../lib/api'

const TIMEZONES = (() => {
  try {
    return Intl.supportedValuesOf('timeZone')
  } catch {
    return []
  }
})()

export default function Locations() {
  useDocumentTitle('Locations')
  const resource = useResource((signal) => api.get('/locations', { signal }).then((res) => res.data.data))
  const [items, setItems] = useState(null)
  const [refreshing, setRefreshing] = useState(false)
  const [refreshError, setRefreshError] = useState(null)
  const [toast, setToast] = useState(null)
  const clearToast = useCallback(() => setToast(null), [])

  async function refresh() {
    setRefreshing(true)
    setRefreshError(null)
    try {
      const res = await api.post('/locations/refresh')
      setItems(res.data.data)
      const count = res.data.data.length
      setToast(`Locations updated: ${count} found.`)
    } catch (err) {
      setRefreshError(errorMessage(err))
    } finally {
      setRefreshing(false)
    }
  }

  function replace(updated) {
    setItems((current) => (current ?? resource.data).map((l) => (l.id === updated.id ? updated : l)))
  }

  const refreshButton = (
    <Button variant="secondary" busy={refreshing} onClick={refresh}>
      {refreshing ? 'Refreshing…' : 'Refresh from Google'}
    </Button>
  )

  return (
    <>
      <div className="flex flex-wrap items-start justify-between gap-4">
        <PageHeader
          title="Locations"
          description="Switch on monitoring and Review Responder checks the location for new reviews each night around 2am in its timezone, then drafts replies for your morning queue."
        />
        {(items ?? resource.data)?.length > 0 && refreshButton}
      </div>

      {refreshError && (
        <Alert tone="error" className="mb-6">
          {refreshError}
        </Alert>
      )}

      <LoadState resource={resource} label="Loading locations">
        {(data) => {
          const locations = items ?? data
          if (locations.length === 0) {
            return (
              <EmptyState title="No locations yet" action={refreshButton}>
                Fetch the locations from your Google Business Profile. Monitoring stays off for each one until you switch it on.
              </EmptyState>
            )
          }
          return (
            <ul className="space-y-4">
              {locations.map((location) => (
                <LocationRow key={location.id} location={location} onSaved={replace} onToast={setToast} />
              ))}
            </ul>
          )
        }}
      </LoadState>

      <Toast message={toast} onDone={clearToast} />
    </>
  )
}

function LocationRow({ location, onSaved, onToast }) {
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)
  const id = `location-${location.id}`
  const zones = TIMEZONES.includes(location.timezone) ? TIMEZONES : [location.timezone, ...TIMEZONES]

  async function save(changes, successMessage) {
    setSaving(true)
    setError(null)
    onSaved({ ...location, ...changes })
    try {
      const res = await api.patch(`/locations/${location.id}`, changes)
      onSaved(res.data.data)
      onToast(successMessage)
    } catch (err) {
      onSaved(location)
      setError(errorMessage(err, "We couldn't save that change. Please try again."))
    } finally {
      setSaving(false)
    }
  }

  return (
    <li className="rounded-card border border-line bg-surface p-5">
      <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-xl">{location.title}</h2>
            {location.is_sample && (
              <span className="rounded-full bg-warn-tint px-2 py-0.5 text-xs font-semibold text-warn">Sample data</span>
            )}
          </div>
          {location.address_line && <p className="mt-1 text-sm text-muted">{location.address_line}</p>}
          <p className="mt-2 text-sm text-muted">
            {location.pending_drafts > 0 ? (
              <Link to="/app" className="font-semibold text-brand underline-offset-2 hover:underline">
                {location.pending_drafts} {location.pending_drafts === 1 ? 'draft' : 'drafts'} waiting
              </Link>
            ) : (
              'No drafts waiting'
            )}
            {' · '}
            {location.last_synced_on ? `Last checked ${formatDate(location.last_synced_on)}` : 'Not checked yet'}
          </p>
        </div>

        <div className="flex flex-wrap items-end gap-x-6 gap-y-3">
          <div>
            <label htmlFor={`${id}-tz`} className="block text-xs font-semibold text-muted">
              Timezone
            </label>
            <select
              id={`${id}-tz`}
              value={location.timezone}
              disabled={saving}
              onChange={(e) => save({ timezone: e.target.value }, `Timezone set to ${e.target.value}.`)}
              className="mt-1 min-h-10 max-w-[16rem] rounded-lg border border-line-strong bg-surface px-3 text-sm disabled:opacity-60"
            >
              {zones.map((zone) => (
                <option key={zone} value={zone}>
                  {zone}
                </option>
              ))}
            </select>
          </div>

          <div className="flex min-h-10 items-center gap-3">
            <label htmlFor={`${id}-monitor`} className="text-sm font-medium">
              Monitor reviews
            </label>
            <button
              id={`${id}-monitor`}
              type="button"
              role="switch"
              aria-checked={location.monitoring}
              aria-disabled={saving || undefined}
              onClick={() =>
                !saving &&
                save(
                  { monitoring: !location.monitoring },
                  location.monitoring ? `Stopped monitoring ${location.title}.` : `Monitoring ${location.title}.`,
                )
              }
              className={`relative inline-flex h-7 w-12 shrink-0 items-center rounded-full border transition-colors aria-disabled:opacity-60 ${
                location.monitoring ? 'border-brand bg-brand' : 'border-line-strong bg-sunken'
              }`}
            >
              <span
                aria-hidden="true"
                className={`inline-block size-5 rounded-full bg-surface shadow transition-transform ${location.monitoring ? 'translate-x-6' : 'translate-x-1'}`}
              />
            </button>
            <span aria-hidden="true" className="w-6 text-xs text-muted">
              {location.monitoring ? 'On' : 'Off'}
            </span>
          </div>
        </div>
      </div>

      {error && (
        <Alert tone="error" className="mt-4">
          {error}
        </Alert>
      )}
    </li>
  )
}

function formatDate(isoDate) {
  const [y, m, d] = isoDate.split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
}
