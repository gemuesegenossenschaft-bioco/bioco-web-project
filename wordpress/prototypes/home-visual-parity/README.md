# biocò Homepage Visual Parity Prototype

Statischer HTML/CSS-Prototyp der biocò-Startseite für visuelle Abgleiche.

## Inhalt

- `index.html` – Startseitenstruktur mit Hero, zweispaltigen Teasern, Events, Schnuppertagen, Aktuelles-Bereich und Footer.
- `styles.css` – Styles, die die Live-Homepage visuell nachbilden.
- `README.md` – Diese Datei.

## Bilder

- Logo: `../../../frontend/public/images/bioco-logo.png` (bereinigtes Live-Asset aus dem Repository)
- Hero- und Teaser-Bilder: exakte CMS-URLs aus dem Live-HTML (`https://cms.bioco.ch/site/assets/files/...`)

## Anzeigen

```bash
python3 -m http.server 8000 --directory ../../..
```

Anschliessend ist der Prototyp unter <http://localhost:8000/wordpress/prototypes/home-visual-parity/> erreichbar.
