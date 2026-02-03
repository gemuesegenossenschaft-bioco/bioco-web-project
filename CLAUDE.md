# biocò (bioco.ch)

Swiss organic vegetable cooperative. Headless CMS: ProcessWire (PHP) on Novatrend cPanel. Frontend: Next.js 14 on Vercel.

## FTP Access (Novatrend cPanel)

- Host: `193.33.128.160` (or `ftp.bioco.ch`), Port: `21`
- User: `gueney@bioco.ch` / Pass: `UEqPP00NAR2ynxU`
- Protocol: Explicit FTPS (FTP_TLS), passive mode required
- Python ftplib connection pattern:
  ```python
  import ftplib, ssl, socket
  socket.setdefaulttimeout(15)
  ctx = ssl.create_default_context()
  ctx.check_hostname = False
  ctx.verify_mode = ssl.CERT_NONE
  ftp = ftplib.FTP_TLS(context=ctx)
  ftp.connect('193.33.128.160', 21, timeout=15)
  ftp.login('gueney@bioco.ch', 'UEqPP00NAR2ynxU')
  ftp.prot_p()
  ftp.set_pasv(True)
  ```
- CMS webroot: `/public_html/cms/`
- Templates: `/public_html/cms/site/templates/`
- Migrations: upload `.php` to `site/templates/`, run via standalone bootstrap script

## Running Migrations

PW `.htaccess` routes all requests through `index.php`, so accessing template files directly returns 404. To run migration scripts:

1. Upload migration script to `site/templates/` (e.g. `install-foo.php`)
2. Create a temporary bootstrap script in **webroot** (`/public_html/cms/`), not in templates:
   ```php
   <?php
   namespace ProcessWire;
   include('./index.php');
   $wire = ProcessWire::getCurrentInstance();
   $pages = $wire->wire('pages');
   $templates = $wire->wire('templates');
   $fields = $wire->wire('fields');
   $modules = $wire->wire('modules');
   $config = $wire->wire('config');
   // ... other API vars as needed
   $config->debug = true;
   include $config->paths->templates . 'install-foo.php';
   ```
3. Upload bootstrap to `/public_html/cms/bootstrap-foo.php`
4. Run via `curl https://cms.bioco.ch/bootstrap-foo.php`
5. **Delete bootstrap script from server after use** (security)

Migration scripts in `site/templates/` use PW API vars (`$pages`, `$fields`, `$modules`, etc.) directly. They output JSON with `success`, `log`, `errors` keys.

## Installed Modules

- `MediaLibrary` (BitPoet): media library tab in CKEditor link/image dialogs. Template: `MediaLibrary`, fields: `MediaImages`, `MediaFiles`. Admin page at `/processwire/media/`.
- `ProcessMediaLibraries`: admin overview of media libraries (auto-installed with MediaLibrary)
- `InputfieldCKEditor`: rich text editor on textarea fields

## CKEditor Fields

All 5 textarea fields use `InputfieldCKEditor` with toolbar, PWImage/PWLink plugins:
`section_text`, `body`, `card_text`, `event_summary`, `event_signup_notes`

## URLs

- Production: https://www.bioco.ch
- CMS Admin: https://cms.bioco.ch/processwire/
- Docs: `/HANDOFF.md`
