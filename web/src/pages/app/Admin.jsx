import { Card, LoadState, PageHeader } from '../../components/ui'
import { useDocumentTitle, useResource } from '../../hooks/useResource'
import { api } from '../../lib/api'

export default function Admin() {
  useDocumentTitle('Admin')
  const health = useResource((signal) => api.get('/admin/health', { signal }).then((r) => r.data))

  return (
    <>
      <PageHeader title="System health" description="Read-only view for administrators." />
      <LoadState resource={health} label="Loading system health">
        {(data) => (
          <div className="grid gap-4 sm:grid-cols-2">
            <Card>
              <p className="text-sm text-muted">Jobs waiting in the queue</p>
              <p className="mt-1 font-display text-3xl font-semibold">{data.queued_jobs}</p>
            </Card>
            <Card>
              <p className="text-sm text-muted">Failed jobs</p>
              <p className={`mt-1 font-display text-3xl font-semibold ${data.failed_jobs > 0 ? 'text-danger' : ''}`}>{data.failed_jobs}</p>
            </Card>
          </div>
        )}
      </LoadState>
    </>
  )
}
