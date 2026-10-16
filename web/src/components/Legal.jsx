/** Shared layout for the Privacy, Terms and FAQ pages. */
export function LegalPage({ title, updated, intro, children }) {
  return (
    <article className="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
      <h1 className="text-3xl sm:text-4xl">{title}</h1>
      {updated && <p className="mt-2 text-sm text-muted">Last updated {updated}</p>}
      {intro && <p className="mt-5 text-lg leading-relaxed text-muted">{intro}</p>}
      <div className="mt-8 space-y-4 leading-relaxed [&_a]:font-semibold [&_a]:text-brand [&_a]:underline [&_a]:underline-offset-2 [&_h2]:pt-6 [&_h2]:text-2xl [&_li]:ml-5 [&_li]:list-disc [&_li]:pl-1 [&_ul]:space-y-2">
        {children}
      </div>
    </article>
  )
}
