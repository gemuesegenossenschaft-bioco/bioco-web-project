import Link from 'next/link'
import Image from 'next/image'

export function Logo() {
  return (
    <Link href="/" className="logo-link">
      <Image
        src="/images/bioco-logo.png"
        alt="biocò Logo"
        width={1693}
        height={1010}
        priority
        style={{ height: 'auto', width: 'auto', maxHeight: '60px' }}
      />
    </Link>
  )
}