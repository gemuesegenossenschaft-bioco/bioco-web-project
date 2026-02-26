import { UtilityNavigation } from './UtilityNavigation'
import { PrimaryNavigation } from './SecondaryNavigation'
import { MobileMenu } from './MobileMenu'

export function Header() {
  return (
    <>
      {/* Utility Nav - Top bar, NOT sticky, scrolls away */}
      <UtilityNavigation />
      <div className="mobile-nav-shell">
        {/* Primary Nav - Main navigation with Logo, sticky on scroll */}
        <PrimaryNavigation />
        {/* Mobile Menu (hamburger) - only visible on mobile */}
        <MobileMenu />
      </div>
    </>
  )
}
