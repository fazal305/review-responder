import { Card, EmptyState, LoadState, PageHeader } from '../../components/ui'
import { useDocumentTitle, useResource } from '../../hooks/useResource'
import { api } from '../../lib/api'

const SERVICE_LABELS = { google: 'Google', llm: 'Drafting model', mail: 'Email' }
const DRAFT_LABELS = { pending_review: 'Waiting for review', published: 'Posted', dismissed: 'Dismissed', draft_failed: 'Failed' }

export default function Admin() {
  useDocumentTitle('Admin')
  const usage = useResource((signal) => api.get('/admin/usage', { signal }).then((r) => r.data))

  return (
    <>
      <PageHeader title="System health" description="Read-only view for administrators. Figures are totals across all accounts; no personal data is shown." />
      <LoadState resource={usage} label="Loading system health">
        {(data) => (
          <div className="space-y-8">
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
              <Stat label="Jobs waiting in the queue" value={data.queued_jobs} />
              <Stat label="Failed jobs" value={data.failed_jobs} alert={data.failed_jobs > 0} />
              <Stat label="Owner accounts" value={data.totals.users} note={`plus ${data.totals.demo_users} demo`} />
              <Stat label="Locations" value={data.totals.locations} note={`${data.totals.monitored_locations} monitored`} />
            </div>

            <section aria-labelledby="usage-heading">
              <h2 id="usage-heading" className="text-xl">
                API usage, last {data.window_days} days
              </h2>
              {data.services.length === 0 ? (
                <div className="mt-4">
                  <EmptyState title="No API calls yet">Calls to Google, the drafting model and the email provider will appear here.</EmptyState>
                </div>
              ) : (
                <Table
                  caption={`API calls in the last ${data.window_days} days, by service and action`}
                  head={['Service', 'Action', 'Calls', 'Failures', 'Avg. time']}
                  numeric={[2, 3, 4]}
                  rows={data.services.map((s) => [
                    SERVICE_LABELS[s.service] ?? s.service,
                    <code key="a" className="text-xs [overflow-wrap:anywhere]">{s.action}</code>,
                    s.calls.toLocaleString(),
                    <span key="f" className={s.failures > 0 ? 'font-semibold text-danger' : ''}>
                      {s.failures.toLocaleString()}
                    </span>,
                    `${s.avg_duration_ms.toLocaleString()} ms`,
                  ])}
                />
              )}
            </section>

            <section aria-labelledby="drafts-heading">
              <h2 id="drafts-heading" className="text-xl">
                Drafts by status
              </h2>
              <Table
                caption="Number of drafts in each status, all time"
                head={['Status', 'Drafts']}
                numeric={[1]}
                rows={Object.entries(data.totals.drafts).map(([status, total]) => [DRAFT_LABELS[status] ?? status, total.toLocaleString()])}
              />
            </section>

            <section aria-labelledby="failures-heading">
              <h2 id="failures-heading" className="text-xl">
                Recent failures
              </h2>
              {data.recent_failures.length === 0 ? (
                <p className="mt-3 text-sm text-muted">No failed calls recorded.</p>
              ) : (
                <Table
                  caption="The 20 most recent failed API calls"
                  head={['When', 'Service', 'Action']}
                  rows={data.recent_failures.map((f) => [
                    <time key="t" dateTime={f.created_at}>
                      {new Date(f.created_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })}
                    </time>,
                    SERVICE_LABELS[f.service] ?? f.service,
                    <code key="a" className="text-xs [overflow-wrap:anywhere]">{f.action}</code>,
                  ])}
                />
              )}
            </section>
          </div>
        )}
      </LoadState>
    </>
  )
}

function Stat({ label, value, note, alert = false }) {
  return (
    <Card className="p-4 sm:p-5">
      <p className="text-sm text-muted">{label}</p>
      <p className={`mt-1 font-display text-3xl font-semibold ${alert ? 'text-danger' : ''}`}>{value.toLocaleString()}</p>
      {note && <p className="mt-0.5 text-xs text-muted">{note}</p>}
    </Card>
  )
}

/** A plain table that scrolls inside its own box on narrow screens instead of widening the page. */
function Table({ caption, head, rows, numeric = [] }) {
  const align = (i) => (numeric.includes(i) ? 'text-right' : 'text-left')
  return (
    <div className="mt-4 overflow-x-auto rounded-card border border-line bg-surface" tabIndex={0} role="region" aria-label={caption}>
      <table className="w-full text-sm">
        <caption className="sr-only">{caption}</caption>
        <thead className="bg-sunken/60 text-xs text-muted">
          <tr>
            {head.map((h, i) => (
              <th key={h} scope="col" className={`px-4 py-2.5 font-semibold whitespace-nowrap ${align(i)}`}>
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, r) => (
            <tr key={r} className="border-t border-line">
              {row.map((cell, i) =>
                i === 0 ? (
                  <th key={i} scope="row" className={`px-4 py-2.5 font-medium ${align(i)}`}>
                    {cell}
                  </th>
                ) : (
                  <td key={i} className={`px-4 py-2.5 tabular-nums ${align(i)}`}>
                    {cell}
                  </td>
                ),
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
