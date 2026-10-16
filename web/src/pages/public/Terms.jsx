import { Link } from 'react-router'
import { LegalPage } from '../../components/Legal'
import { useDocumentTitle } from '../../hooks/useResource'

export default function Terms() {
  useDocumentTitle('Terms of Service')
  return (
    <LegalPage
      title="Terms of Service"
      updated="16 October 2026"
      intro="These terms apply when you use Review Responder, a tool run by Fazal Abbas. By signing in or trying the demo you agree to them."
    >
      <h2>Who can use it</h2>
      <p>
        You must be 18 or older. If you connect a Google Business Profile, you confirm that you are authorized to manage it and to reply
        to its reviews on behalf of the business.
      </p>

      <h2>What the service does</h2>
      <p>
        Review Responder fetches new Google reviews for the locations you choose to monitor, drafts a reply to each one, and posts a reply
        to Google only when you press Approve &amp; Post. Drafts may be written by a language model or from fixed templates. Either way
        they can be wrong, miss context or sound off, so read each one before you post it.
      </p>

      <h2>Your responsibilities</h2>
      <ul>
        <li>You are responsible for every reply you post, including any edits you make, as if you had written it yourself.</li>
        <li>You must follow Google&apos;s terms and policies for Business Profiles and reviews.</li>
        <li>
          Do not use Review Responder to post misleading, abusive or unlawful replies, to harass reviewers, to manipulate reviews, or to
          interfere with the service or other people&apos;s accounts.
        </li>
      </ul>

      <h2>Your content</h2>
      <p>
        You keep ownership of your content: your business information, settings, edits and replies. You give us a limited permission to
        store and process it, including sending it to Google and to the drafting model provider described in the{' '}
        <Link to="/privacy">Privacy Policy</Link>, only as needed to run the service for you. Reviews belong to their authors and are
        shown here as Google provides them.
      </p>

      <h2>The demo</h2>
      <p>
        Demo accounts use fictional sample businesses, reviewers and reviews. Any resemblance to real people or businesses is
        coincidental. Nothing in a demo account is connected to Google or posted anywhere, and demo accounts may be removed at any time.
      </p>

      <h2>Availability and changes</h2>
      <p>
        Review Responder depends on Google&apos;s APIs and other providers and may be unavailable, limited or changed at any time. Google
        sign-in works only once Google has approved this project&apos;s access to the Business Profile APIs.
      </p>

      <h2>No warranty and limits of liability</h2>
      <p>
        The service is provided &ldquo;as is&rdquo;, without warranties of any kind. To the extent the law allows, we are not liable for
        indirect or consequential losses, or for replies you post, missed or delayed reviews, or problems caused by Google or other
        providers.
      </p>

      <h2>Ending your use</h2>
      <p>
        You can stop at any time and delete your account from Settings. We may suspend accounts that break these terms or put the service
        at risk.
      </p>

      <h2>Copyright and licensing</h2>
      <p>
        Site content © 2026 Fazal Abbas. The Review Responder source code is licensed under the PolyForm Noncommercial License 1.0.0;
        commercial use of the code requires a separate paid license.
      </p>

      <h2>Changes and contact</h2>
      <p>
        If these terms change, we will update this page and the date above. Questions:{' '}
        <a href="mailto:fazalabbas2002@gmail.com">fazalabbas2002@gmail.com</a>.
      </p>
    </LegalPage>
  )
}
