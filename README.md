# Woodstock Cabinet Makers — Website

Static site (HTML/CSS/JS + PHP) for **woodstockcabinetmakers.com.au**, built to run on Hostinger shared hosting with no build step and no Node server. **Live** at woodstockcabinetmakers.com.au as of 25 Oct 2026, on the Hostinger Premium plan hosting account.

Read `_context/website-status.md` first — it has current build status, what's still pending, and the hard rules for this project (don't invent business facts, nothing goes live without Jack's sign-off, etc.).

## Structure

```
index.html                 Home page
projects/                  Portfolio gallery + one page per project
services/                  5 service pages (kitchen, bedroom, bathroom, office, garage)
assets/css/style.css       Full design system (brand palette, type, components)
assets/js/main.js          Nav, chat widget, forms, live reviews fetch, scroll reveal
assets/img/                Logo (real, extracted from brand assets) + placeholder photos
api/contact-form.php       Contact + quick-chat form handler (PHP mail())
api/google-reviews-proxy.php   Server-side Google Places API (New) proxy, with caching
api/config.sample.php      Copy to api/config.php on the server and fill in real values
admin/index.php            Password-protected photo-upload tool (not linked publicly)
assets/img/photos/         Real project photos go here — filenames are fixed, see below
_context/                  Brand voice, brand context, ideal customer profile, build status
```

## Adding real photos

Open `/admin` on the live site, log in, and:

- **Cover photo** (one per project, used on cards/thumbnails sitewide) and the
  **homepage hero** — upload a file, or paste a single Drive file's share link.
- **Project gallery** (any number of photos, shown on that project's own page) —
  paste a link to a whole **Drive folder** and every image in it becomes the
  gallery, replacing whatever was there before. The first two photos (by filename
  order in Drive — rename files in Drive, e.g. `1 ...`, `2 ...`, to control this)
  show larger in a featured grid; every photo opens full-screen in a lightbox on
  click. No fixed slots, no code changes — drop 3 photos or 30.

Either way the server resizes (max 2400px edge), auto-orients and compresses each
photo to a sensible web size automatically.

Both the single-link and folder-link options need the Drive file/folder shared as
**"Anyone with the link"** — the server has no one's personal Google login, so a
private link just fails with a clear error. The folder importer additionally needs
a Google Cloud API key (see below); the single-link/upload options work without it.

`/admin` is not linked from the public site and is excluded in `robots.txt`, but
it is only as secure as its password (`ADMIN_PASSWORD_HASH` in `api/config.php`) —
treat that page link and password like any other login.

### Setting up the Google Cloud API key (for folder import + live reviews)

Both the Drive folder importer and live Google Reviews (optional, has a curated
fallback) use one Google Cloud API key. ~5 minutes, free:

1. Go to [console.cloud.google.com](https://console.cloud.google.com) and create a
   new project (or pick an existing one) — top-left project dropdown → "New Project".
2. Enable the APIs: go to "APIs & Services" → "Library", search for and enable
   **"Google Drive API"**, and also **"Places API (New)"** if you want live reviews.
3. Create a key: "APIs & Services" → "Credentials" → "Create Credentials" → "API key".
4. Restrict it (important — an unrestricted key is a liability): click the new key,
   under "API restrictions" choose "Restrict key" and select Drive API (+ Places API
   if enabled), under "Application restrictions" choose "IP addresses" and add your
   Hostinger server's outbound IP (ask Hostinger support or check hPanel if unsure).
5. Paste the key into `api/config.php` as `GOOGLE_API_KEY` (and `GOOGLE_PLACES_API_KEY`
   if reusing it for reviews too — same key value works for both fields).
6. Billing: Drive API's free tier comfortably covers this use (a handful of folder
   listings a month); Google requires a billing account attached to the project even
   for free-tier usage, but you won't be charged at this volume.

## Local preview

Any static file server works for looking at the pages, but the contact form and live
reviews need PHP, so use PHP's built-in server from the repo root:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/`. Without a local `api/config.php`, the Google Reviews
proxy will fail gracefully and the page falls back to the real curated quotes already in
`index.html` — this is expected and by design.

## Deploying to Hostinger

1. In hPanel, upload the contents of this repo to the `public_html` (or equivalent) folder
   for the woodstockcabinetmakers.com.au domain — via File Manager, FTP, or Git deployment.
2. Copy `api/config.sample.php` to `api/config.php` **directly on the server** (it's
   gitignored — never commit real secrets) and fill in the Google Places API key and the
   contact "from" email. See the comments in that file for exactly what's needed.
3. Confirm the domain's nameservers point to Hostinger (per the brief, they were still
   parked at `dns-parking.com` as of the last check) and that this is the live site for
   the domain, not a different one.
4. Test the contact form end-to-end (submit it, confirm the email actually reaches
   `jack@woodcab.com.au` — PHP `mail()` deliverability depends on the mailbox/alias and
   SPF/DKIM setup in hPanel, so this needs a real test, not just "the form submitted").
5. Test the Google Reviews section loads live data (or confirm the curated fallback still
   looks right if the API key isn't set up yet).
6. Swap every placeholder photo under `assets/img/photos/` for the real thing — see
   "Adding real photos" above. Do this via `/admin` rather than FTP where possible, since
   it also resizes/compresses correctly.
7. Screenshot desktop + mobile and get Jack's sign-off before pointing the QR code /
   announcing the site live.

## Design system

Colours, type (Italiana / Lora / Work Sans) and component styles follow the Woodstock
Brand & Visual Guidelines (Aug 2026) exactly — see the CSS custom properties at the top of
`assets/css/style.css` for the palette, and `_context/brand-voice.md` for copy tone rules
before changing any text on the site.
