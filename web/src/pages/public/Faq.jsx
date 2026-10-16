import { Link } from 'react-router'
import { LegalPage } from '../../components/Legal'
import { useDocumentTitle } from '../../hooks/useResource'

const QUESTIONS = [
  {
    q: 'Is the demo connected to real Google reviews?',
    a: (
      <p>
        No. &ldquo;Try the demo&rdquo; creates a private, throwaway account with fictional sample bakeries and reviews, labelled
        &ldquo;Sample data&rdquo;. Approving a demo reply only marks it as posted; nothing is sent to Google. Demo accounts are deleted
        after 24 hours.
      </p>
    ),
  },
  {
    q: 'Why can’t I sign in with Google?',
    a: (
      <p>
        Managing reviews needs Google&apos;s Business Profile APIs, and Google must approve each project before it can use them. Until
        that approval is in place, Google sign-in is either switched off or limited to test accounts. The demo works without it.
      </p>
    ),
  },
  {
    q: 'Are the replies written by AI?',
    a: (
      <p>
        It depends on how the server is set up. With a drafting model configured (through OpenRouter), each draft is written by a
        language model and labelled with the model&apos;s name. Without one, drafts come from fixed star-based templates and are labelled
        &ldquo;Template draft&rdquo;. Either way, read and edit each draft before posting.
      </p>
    ),
  },
  {
    q: 'Does it ever post a reply on its own?',
    a: <p>No. A reply goes to Google only when you press Approve &amp; Post on that draft.</p>,
  },
  {
    q: 'When do new reviews show up?',
    a: (
      <p>
        Once a night, around 2am in each monitored location&apos;s own timezone, Review Responder fetches the previous day&apos;s reviews
        and drafts replies, so they are waiting in the morning. The check can run a little late if the server&apos;s scheduler is
        delayed. Reviews you already answered on Google are skipped.
      </p>
    ),
  },
  {
    q: 'When does the morning email arrive?',
    a: (
      <p>
        At the hour you choose in Settings, in your timezone, and only on days with new drafts to review. Demo accounts never receive
        email.
      </p>
    ),
  },
  {
    q: 'What happens to the review text?',
    a: (
      <p>
        It is stored so you can see it in your queue, and when AI drafting is on it is sent to the drafting model to write the reply. The{' '}
        <Link to="/privacy">Privacy Policy</Link> has the details.
      </p>
    ),
  },
  {
    q: 'How do I delete my data?',
    a: <p>Go to Settings and choose Delete account. Everything is removed and Google access is revoked.</p>,
  },
]

export default function Faq() {
  useDocumentTitle('FAQ')
  return (
    <LegalPage title="Frequently asked questions">
      {QUESTIONS.map(({ q, a }) => (
        <section key={q}>
          <h2>{q}</h2>
          <div className="mt-2">{a}</div>
        </section>
      ))}
    </LegalPage>
  )
}
