# Woodstock Cabinet Makers — Website

Static site (HTML/CSS/JS + PHP) for **woodstockcabinetmakers.com.au**, built to run on Hostinger shared hosting with no build step and no Node server.

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
_context/                  Brand voice, brand context, ideal customer profile, build status
```

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
6. Swap every placeholder image (`assets/img/placeholder-photo.svg`) for real project
   photography once supplied — filenames/alt text are already written per photo, see
   `_context/website-status.md` for how that handoff is expected to work.
7. Screenshot desktop + mobile and get Jack's sign-off before pointing the QR code /
   announcing the site live.

## Design system

Colours, type (Italiana / Lora / Work Sans) and component styles follow the Woodstock
Brand & Visual Guidelines (Aug 2026) exactly — see the CSS custom properties at the top of
`assets/css/style.css` for the palette, and `_context/brand-voice.md` for copy tone rules
before changing any text on the site.
