import { useEffect, useRef } from 'react'
import { Link } from 'react-router'

const buttonBase =
  'inline-flex items-center justify-center gap-2 rounded-lg px-4 min-h-10 text-sm font-semibold transition-colors disabled:opacity-60 disabled:cursor-not-allowed aria-disabled:opacity-60 aria-disabled:cursor-not-allowed'

const buttonVariants = {
  primary: 'bg-brand text-white hover:bg-brand-strong',
  secondary: 'bg-surface text-ink border border-line-strong hover:bg-sunken',
  ghost: 'text-ink hover:bg-sunken',
  danger: 'bg-danger text-white hover:brightness-95',
}

export function Button({ variant = 'primary', busy = false, className = '', children, onClick, type = 'button', ...props }) {
  return (
    <button
      type={type}
      className={`${buttonBase} ${buttonVariants[variant]} ${className}`}
      aria-disabled={busy || undefined}
      onClick={(e) => {
        if (busy) {
          e.preventDefault()
          return
        }
        onClick?.(e)
      }}
      {...props}
    >
      {busy && <Spinner />}
      {children}
    </button>
  )
}

export function LinkButton({ variant = 'primary', className = '', ...props }) {
  return <Link className={`${buttonBase} ${buttonVariants[variant]} ${className}`} {...props} />
}

export function Spinner() {
  return <span aria-hidden="true" className="inline-block size-4 animate-spin rounded-full border-2 border-current border-r-transparent" />
}

const alertTones = {
  error: 'border-danger/30 bg-danger-tint text-danger',
  warn: 'border-warn/30 bg-warn-tint text-warn',
  success: 'border-success/30 bg-success-tint text-success',
  info: 'border-line-strong bg-surface text-ink',
}

export function Alert({ tone = 'info', title, children, className = '' }) {
  return (
    <div role={tone === 'error' ? 'alert' : 'status'} className={`rounded-lg border px-4 py-3 text-sm ${alertTones[tone]} ${className}`}>
      {title && <p className="font-semibold">{title}</p>}
      {children && <div className={title ? 'mt-0.5' : ''}>{children}</div>}
    </div>
  )
}

export function Skeleton({ className = '' }) {
  return <div aria-hidden="true" className={`skeleton ${className}`} />
}

/** Renders loading (skeleton), slow-network, and error states around a resource. */
export function LoadState({ resource, label = 'Loading', children }) {
  if (resource.loading) {
    return (
      <div aria-busy="true">
        <span className="sr-only" role="status">
          {label}…
        </span>
        {resource.slow && (
          <Alert tone="warn" className="mb-4">
            This is taking longer than usual. Your connection may be slow — we&apos;re still trying.
          </Alert>
        )}
        <div className="space-y-3">
          <Skeleton className="h-16" />
          <Skeleton className="h-16" />
        </div>
      </div>
    )
  }
  if (resource.error) return <ErrorState message={resource.error} onRetry={resource.reload} />
  return children(resource.data)
}

export function ErrorState({ title = "Couldn't load this", message, onRetry }) {
  return (
    <div role="alert" className="rounded-card border border-line bg-surface px-6 py-10 text-center">
      <p className="font-display text-lg font-semibold">{title}</p>
      <p className="mx-auto mt-1 max-w-md text-sm text-muted">{message}</p>
      {onRetry && (
        <Button variant="secondary" className="mt-5" onClick={onRetry}>
          Try again
        </Button>
      )}
    </div>
  )
}

export function EmptyState({ title, children, action }) {
  return (
    <div className="rounded-card border border-dashed border-line-strong bg-surface/60 px-6 py-14 text-center">
      <p className="font-display text-xl font-semibold">{title}</p>
      {children && <div className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">{children}</div>}
      {action && <div className="mt-5 flex flex-wrap justify-center gap-3">{action}</div>}
    </div>
  )
}

export function PageHeader({ title, description }) {
  return (
    <div className="mb-8">
      <h1 className="text-3xl sm:text-4xl">{title}</h1>
      {description && <p className="mt-2 max-w-2xl text-muted">{description}</p>}
    </div>
  )
}

export function Card({ className = '', children, ...props }) {
  return (
    <section className={`min-w-0 rounded-card border border-line bg-surface p-5 ${className}`} {...props}>
      {children}
    </section>
  )
}

export function ConfirmDialog({ open, title, children, confirmLabel = 'Confirm', busy, onConfirm, onCancel }) {
  const ref = useRef(null)
  useEffect(() => {
    const dialog = ref.current
    if (!dialog) return
    if (open && !dialog.open) dialog.showModal()
    if (!open && dialog.open) dialog.close()
  }, [open])

  return (
    <dialog
      ref={ref}
      onCancel={(e) => {
        e.preventDefault()
        onCancel()
      }}
      className="m-auto w-[min(28rem,calc(100vw-2rem))] rounded-card border border-line bg-surface p-0 text-ink shadow-xl backdrop:bg-ink/40"
      aria-labelledby="confirm-title"
    >
      <div className="p-6">
        <h2 id="confirm-title" className="text-xl">
          {title}
        </h2>
        <div className="mt-2 text-sm text-muted">{children}</div>
        <div className="mt-6 flex flex-wrap justify-end gap-2">
          <Button variant="secondary" onClick={onCancel}>
            Cancel
          </Button>
          <Button variant="danger" busy={busy} onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </div>
      </div>
    </dialog>
  )
}
