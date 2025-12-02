import { UtilityNavigation } from './UtilityNavigation'
import { PrimaryNavigation } from './SecondaryNavigation'
import { Logo } from './Logo'
import { MobileMenu } from './MobileMenu'

export function Header() {
  return (
    <>
      {/* Utility Nav - Top bar, NOT sticky, scrolls away */}
      <UtilityNavigation />
      {/* Primary Nav - Main navigation, sticky on scroll */}
      <PrimaryNavigation />
      <header id="header">
        <div className="header-top">
          <div id="header-logo" className="header-logo">
            <Logo />
          </div>
          <MobileMenu />
        </div>
      </header>
    </>
  )
}