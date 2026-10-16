import { EmptyState, PageHeader } from '../../components/ui'
import { useDocumentTitle } from '../../hooks/useResource'

export default function Locations() {
  useDocumentTitle('Locations')
  return (
    <>
      <PageHeader title="Locations" description="Choose which of your business locations Review Responder watches for new reviews." />
      <EmptyState title="No locations yet">
        Your Google Business Profile locations will be listed here once your account is connected. Monitoring is off for each
        location until you switch it on.
      </EmptyState>
    </>
  )
}
