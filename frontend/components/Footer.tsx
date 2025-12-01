import Link from 'next/link'

export function Footer() {
  return (
    <footer id="footer">
      <div id="footer-content">
        <section id="J-01">
          <div className="footer-grid">
            <div className="footer-column">
              <h3>Navigation</h3>
              <ul>
                <li><Link href="/kontakt">Kontakt</Link></li>
                <li><Link href="/impressum">Impressum</Link></li>
                <li><Link href="/datenschutz">Datenschutz</Link></li>
                <li><Link href="/statuten">Statuten</Link></li>
              </ul>
            </div>

            <div className="footer-column">
              <h3>Kontakt</h3>
              <p>
                <strong>Gemüsegenossenschaft biocò</strong><br />
                Geisshof<br />
                5412 Gebenstorf<br />
                Aargau, Schweiz
              </p>
              <p>
                <a href="mailto:info@bioco.ch">info@bioco.ch</a>
              </p>
              <p style={{ marginTop: '12px', fontSize: '14px' }}>
                <strong>ab 16:00 uhr abholbereit</strong>
              </p>
            </div>

            <div className="footer-column">
              <h3>Social Media</h3>
              <div className="social-links">
                <a 
                  href="https://www.instagram.com/bioco.ch" 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="social-link"
                  aria-label="Instagram"
                >
                  Instagram
                </a>
                <a 
                  href="https://www.facebook.com/bioco.ch" 
                  target="_blank" 
                  rel="noopener noreferrer"
                  className="social-link"
                  aria-label="Facebook"
                >
                  Facebook
                </a>
              </div>
            </div>

            <div className="footer-column">
              <h3>Dokumente</h3>
              <ul>
                <li>
                  <a 
                    href="https://bioco.ch/wp-content/uploads/2018/01/1801_Beitritts-Erklaerung.pdf" 
                    target="_blank" 
                    rel="noopener noreferrer"
                  >
                    Statuten (PDF)
                  </a>
                </li>
                <li>
                  <a 
                    href="https://bioco.ch/wp-content/uploads/2023/12/2023-Abo-Bestellung.pdf" 
                    target="_blank" 
                    rel="noopener noreferrer"
                  >
                    Reglement (PDF)
                  </a>
                </li>
              </ul>
            </div>
          </div>

          <div className="footer-partners">
            <h3>Partner & Zertifizierungen</h3>
            <div className="partner-badges">
              <a 
                href="https://www.demeter.ch" 
                target="_blank" 
                rel="noopener noreferrer"
                className="partner-badge"
              >
                <span>Demeter</span>
              </a>
              <a 
                href="https://www.bio-suisse.ch" 
                target="_blank" 
                rel="noopener noreferrer"
                className="partner-badge"
              >
                <span>Bio Suisse</span>
              </a>
            </div>
            <p style={{ marginTop: '16px', fontSize: '14px', color: 'var(--text-secondary)' }}>
              Solidarische Landwirtschaft für Baden, Brugg, Gebenstorf, Wettingen und die Region Aargau
            </p>
          </div>
        </section>
      </div>
    </footer>
  )
}
