import { Logo } from './Logo'

export function MinimalHeader() {
  return (
    <header id="header" className="minimal-header">
      <div className="header-top">
        <div id="header-logo" className="header-logo">
          <Logo />
        </div>
      </div>
    </header>
  )
}
