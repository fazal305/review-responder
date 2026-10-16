import { Link } from 'react-router'
import { LegalPage } from '../../components/Legal'
import { useDocumentTitle } from '../../hooks/useResource'

export default function Privacy() {
  useDocumentTitle('Privacy Policy')
  return (
    <LegalPage
      title="Privacy Policy"
      updated="16 October 2026"
      intro="Review Responder is a small tool run by Fazal Abbas. This page explains what it stores about you, who else sees it, and how to remove it."
    >
      <h2>What we collect</h2>
      <ul>
        <li>
          <strong>Your Google profile:</strong> your name, email address and Google account ID, received when you sign in with Google.
        </li>
        <li>
          <strong>Google access tokens:</strong> the tokens that let Review Responder read your reviews and post the replies you approve. They
          are encrypted in our database and are never sent to your browser.
        </li>
        <li>
          <strong>Your business data:</strong> the Business Profile locations you manage (name, address, timezone, whether monitoring is
          on), the reviews we fetch for monitored locations (reviewer display name, star rating, review text and date), the reply drafts,
          your edits, and the replies you post.
        </li>
        <li>
          <strong>Your settings:</strong> reply guidelines (tone, sign-off, phrases to avoid, notes) and morning email preferences.
        </li>
        <li>
          <strong>Usage records:</strong> for each call to Google, the drafting model or the email provider we record the type of call,
          whether it worked and how long it took. These records never contain review text.
        </li>
      </ul>
      <p>We use this data only to run Review Responder for you. We do not sell it, and we do not use it for advertising.</p>

      <h2>Who else processes it</h2>
      <ul>
        <li>
          <strong>Google</strong>, to sign you in, list your locations, fetch reviews and post your approved replies. Our use of
          information received from Google APIs adheres to the Google API Services User Data Policy, including the Limited Use
          requirements.
        </li>
        <li>
          <strong>OpenRouter</strong>, when AI drafting is switched on: to write a draft we send the review text, star rating, reviewer
          display name, business name and your reply guidelines to OpenRouter, which passes them to the language model that writes the
          reply. OpenRouter and the model provider handle this under their own privacy policies; some free models may keep prompts. When
          AI drafting is off, drafts come from fixed templates and nothing is sent.
        </li>
        <li>
          <strong>Our email provider</strong> (for example Mailgun, Amazon SES or another SMTP service), which receives your name, email
          address and the summary content in order to deliver the morning email.
        </li>
        <li>
          <strong>Our hosting providers</strong>, which run the website and the server and store the database. They may keep standard
          access logs under their own policies.
        </li>
      </ul>

      <h2>IP addresses</h2>
      <p>
        Your IP address is used briefly to limit how often requests can be made, which protects the service from abuse. These counters
        live in a short-lived cache and expire within a day. IP addresses are not attached to your account or kept in any lasting record.
      </p>

      <h2>Cookies and browser storage</h2>
      <p>
        Review Responder sets no cookies and uses no analytics, tracking pixels or advertising scripts. When you sign in, a sign-in token
        is kept in your browser&apos;s local storage so you stay signed in; signing out removes it. During Google sign-in a one-time
        value is kept in session storage to tie the sign-in to your browser tab. Because nothing here is used for tracking or is otherwise
        non-essential, there is no cookie banner.
      </p>

      <h2>How long we keep it</h2>
      <p>
        We keep your data while your account exists. Demo accounts hold only fictional sample data and are deleted, with
        everything in them, 24 hours after they are created.
      </p>

      <h2>Deleting your data</h2>
      <p>
        Use <strong>Settings → Delete account</strong> to permanently remove your account, locations, reviews, drafts and settings. This
        also revokes Review Responder&apos;s access to your Google account. You can also remove that access yourself at any time from
        your Google Account&apos;s third-party access page. Replies you already posted stay on Google, where you can edit or delete them.
      </p>

      <h2>Age</h2>
      <p>Review Responder is for business owners and managers aged 18 or older. We do not knowingly collect data from anyone younger.</p>

      <h2>Changes and contact</h2>
      <p>
        If this policy changes, we will update this page and the date above. Questions or requests about your data:{' '}
        <a href="mailto:fazalabbas2002@gmail.com">fazalabbas2002@gmail.com</a>. See also the <Link to="/terms">Terms of Service</Link>.
      </p>
    </LegalPage>
  )
}
