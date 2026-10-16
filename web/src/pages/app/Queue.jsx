import { EmptyState, LinkButton, PageHeader } from '../../components/ui'
import { useDocumentTitle } from '../../hooks/useResource'

export default function Queue() {
  useDocumentTitle('Queue')
  return (
    <>
      <PageHeader title="This morning's queue" description="Reply drafts waiting for your review, grouped by location." />
      <EmptyState title="Inbox zero" action={<LinkButton variant="secondary" to="/app/locations">Go to locations</LinkButton>}>
        No drafts are waiting. Drafts appear here after Review Responder checks a monitored location for new reviews.
      </EmptyState>
    </>
  )
}
