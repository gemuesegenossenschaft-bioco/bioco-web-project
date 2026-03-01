import Link from 'next/link'

export function Logo() {
  return (
    <Link href="/" className="logo-link">
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src="/images/bioco-logo.png"
        alt="biocò Logo"
        style={{ height: 'auto', width: 'auto', maxHeight: '60px' }}
      />
    </Link>
  )
}