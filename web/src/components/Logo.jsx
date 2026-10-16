export function LogoMark({ className = 'size-7' }) {
  return (
    <svg viewBox="0 0 32 32" className={className} aria-hidden="true">
      <rect width="32" height="32" rx="8" fill="#24365c" />
      <path d="M8 10.5h16v9.5H14l-4.5 3.5V20H8z" fill="none" stroke="#faf6ef" strokeWidth="2.2" strokeLinejoin="round" />
      <circle cx="23.5" cy="9" r="3.5" fill="#d98a1c" />
    </svg>
  )
}

export function Wordmark() {
  return (
    <span className="flex items-center gap-2">
      <LogoMark />
      <span className="font-display text-lg font-semibold tracking-tight">Review Responder</span>
    </span>
  )
}
