# CMS Pages Setup Guide

## Pages to Create in ProcessWire

Create these pages under **Pages → content** with template `page_content`:

### 1. /content/mitmachen/
- **Title:** Mitmachen
- **Template:** page_content
- **Sections to add:**
  - Section ID: `mitarbeit`
    - Title: "Was es braucht, damit wir gesundes Gemüse haben"
    - Layout: rich_text
  - Section ID: `gruppen`
    - Title: "Gruppen & Gemeinschaft"
    - Layout: rich_text
  - Section ID: `familien`
    - Title: "Familien & Kinder auf dem Geisshof"
    - Layout: split_media_text
    - Image: Upload family/children photo

### 2. /content/gemuese/
- **Title:** Gemüse
- **Template:** page_content
- **Sections to add:**
  - Section ID: `intro`
    - Title: "Unser Gemüse"
    - Layout: rich_text
  - Section ID: `saisonkalender`
    - Title: "Saisonkalender"
    - Layout: component
    - Component: `gallery` or custom

### 3. /content/solawi/
- **Title:** Solawi
- **Template:** page_content
- **Sections to add:**
  - Section ID: `intro`
    - Title: "Solidarische Landwirtschaft"
    - Layout: rich_text
  - Section ID: `prinzipien`
    - Title: "Die Prinzipien der Solawi"
    - Layout: rich_text
  - Section ID: `praxis`
    - Title: "So funktioniert's"
    - Layout: split_text_media

### 4. /content/abos/
- **Title:** Abos
- **Template:** page_content
- **Sections to add:**
  - Section ID: `intro`
    - Title: "Dein wöchentliches Gemüseabo"
    - Layout: rich_text
  - Section ID: `pricing`
    - Title: "Gemüse-Abos"
    - Layout: rich_text
    - Add pricing table in text

### 5. /content/aktuelles/
- **Title:** Aktuelles
- **Template:** page_content
- **Sections to add:**
  - Section ID: `intro`
    - Title: "Aktuelles"
    - Layout: rich_text

### 6. /content/kontakt/
- **Title:** Kontakt
- **Template:** page_content
- **Sections to add:**
  - Section ID: `kontakt-form`
    - Title: "Kontakt"
    - Layout: component
    - Component: `contact_form`

### 7. /content/standorte-depots/
- **Title:** Standorte & Depots
- **Template:** page_content
- **Sections to add:**
  - Section ID: `intro`
    - Title: "Standorte & Depots"
    - Layout: rich_text
  - Section ID: `map`
    - Title: "Karte"
    - Layout: component
    - Component: `depot_map`

## Simple Text Pages (Create at Root Level)

These can be created directly at root with `basic-page` template:

- **/datenschutz** - Privacy policy
- **/impressum** - Imprint
- **/statuten** - Statutes

## Quick Creation Steps

1. Go to **Pages → content** in ProcessWire admin
2. Click **Add New**
3. Choose template: `page_content`
4. Set title and name
5. Add **content_sections**:
   - Click "+ Add content_sections"
   - Fill in Section ID, Title, Text
   - Choose Layout (split_media_text, rich_text, etc.)
   - Upload images if needed
   - Add buttons if needed
6. Save and Publish

## Testing

After creating a page, test it:
```
https://bioco.ch/{pagename}
```

Wait 60 seconds or redeploy for changes to appear.
