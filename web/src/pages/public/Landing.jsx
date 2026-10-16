import { SignInOptions } from '../../components/SignInOptions'
import { useDocumentTitle } from '../../hooks/useResource'

const STEPS = [
  {
    title: 'Overnight, new reviews come in',
    body: 'For each location you choose to monitor, Review Responder fetches the last day of Google reviews in the early hours, local time.',
  },
  {
    title: 'A reply is drafted for each one',
    body: 'Every review gets a star rating, a sentiment read and a suggested reply written to your tone, sign-off and phrases to avoid.',
  },
  {
    title: 'You approve over morning coffee',
    body: 'Edit any draft, ask for a softer or shorter version, or dismiss it. Nothing is posted until you press Approve & Post.',
  },
]

export default function Landing() {
  useDocumentTitle(null)
  return (
    <>
      <section className="mx-auto grid max-w-5xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:py-24">
        <div>
          <p className="text-sm font-semibold tracking-wide text-muted uppercase">For local business owners</p>
          <h1 className="mt-3 text-4xl leading-tight sm:text-5xl">Your Google reviews, answered by breakfast.</h1>
          <p className="mt-5 max-w-xl text-lg leading-relaxed text-muted">
            Review Responder reads new reviews for your Google Business Profile locations, drafts a professional reply to each one
            overnight, and leaves them in a morning queue for you to edit and approve.
          </p>
        </div>
        <div className="rounded-card border border-line bg-surface p-6">
          <h2 className="text-xl">Get started</h2>
          <p className="mt-1 mb-5 text-sm text-muted">Sign in with the Google account that manages your business, or look around with a demo.</p>
          <SignInOptions />
        </div>
      </section>

      <section aria-labelledby="how" className="border-t border-line bg-sunken/50">
        <div className="mx-auto max-w-5xl px-4 py-16 sm:px-6">
          <h2 id="how" className="text-2xl sm:text-3xl">
            How a morning works
          </h2>
          <ol className="mt-8 grid gap-6 md:grid-cols-3">
            {STEPS.map((step, i) => (
              <li key={step.title} className="rounded-card border border-line bg-surface p-5">
                <p className="font-display text-3xl font-semibold text-brand" aria-hidden="true">
                  {i + 1}
                </p>
                <h3 className="mt-2 text-lg">{step.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-muted">{step.body}</p>
              </li>
            ))}
          </ol>
          <p className="mt-8 max-w-2xl text-sm text-muted">
            Replies are only ever posted when you approve them. Your Google access is stored encrypted on the server and never sent to
            your browser.
          </p>
        </div>
      </section>
    </>
  )
}
