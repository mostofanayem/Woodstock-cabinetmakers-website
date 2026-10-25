# Website Build Status — Woodstock Cabinet Makers

**Live since 25 Oct 2026** at woodstockcabinetmakers.com.au, deployed to the Hostinger Premium plan hosting account (same account as aashirvadwellness.com.au, not touched). Deployed via the Hostinger API connector — files pushed directly, domain parked onto the website, DNS/SSL handled automatically by Hostinger. Real photos are added via `/admin` (see README) rather than redeploying code.

Read this first if you're picking this repo up fresh. Also read `brand-voice.md`, `brand-context.md` and `ideal-customer-profile.md` in this folder before writing or changing any copy.

## What this repo is

A static HTML/CSS/JS + PHP website for **woodstockcabinetmakers.com.au**, built to deploy on Hostinger shared hosting (no Node server — static files uploaded via File Manager/FTP/Git, PHP for the contact form and the Google Reviews proxy). No build step required.

## Decisions made (confirmed with Mostofa, 24 Oct 2026)

- **Google Reviews**: live via Google Places API (New), through a server-side PHP proxy (`api/google-reviews-proxy.php`) — not a paid widget, not purely static. See "Still needed before launch" below for the API key.
- **Contact form backend**: Hostinger's native PHP `mail()` (`api/contact-form.php`), not a third-party form service.
- **Portfolio scope for this build**: the 4 projects named in the original brief — Lisa's Kitchen, Marie's Fireplace & Cabinetry, Debbie's Entertainment Wall/Kitchen/Wardrobe, Jennifer's Queenslander Renovation. (See "More real projects exist" below — there's more material than this if Jack/Mostofa want to expand later.)
- Build approach is plain static HTML/CSS/JS/PHP, not a framework or AI site builder (e.g. Lovable) — matches what the original plan recommended and what Hostinger's shared plan supports without a build step.

## What's built

- Home page: hero, floating nav (dropdown services, phone + rating strip, socials), Google Reviews strip (live-fetch with curated real-quote fallback), floating chat widget, horizontally-scrolling projects band, contact section + form, footer.
- `/projects/` — portfolio gallery (all 4 projects) + one detail page per project, each with the real approved copy/quotes from the brief.
- `/services/` — all 5 service pages (Kitchen & Living Room, Bedroom & Wardrobes, Bathroom & Laundry, Home & Work Office, Garage Storage), each linking to relevant real projects where one exists.
- `api/contact-form.php` — validated, honeypot-protected, emails `jack@woodcab.com.au` via PHP `mail()`.
- `api/google-reviews-proxy.php` — resolves the Place ID, fetches rating + reviews from Places API (New), caches locally (default 6h) to control API cost, fails gracefully to the page's built-in curated reviews.
- Real brand logo extracted and sized (`assets/img/logo-icon.png`, favicons, apple-touch-icon) from the brand asset zip Mostofa supplied.
- SEO basics: per-page titles/descriptions, canonical URLs, Open Graph tags, `LocalBusiness`/`HomeAndConstructionBusiness` JSON-LD with the real address/phone/geo, `sitemap.xml`, `robots.txt`, custom `404.html`.
- Scroll-reveal animation and hero entrance motion (progressive enhancement — degrades to fully visible with no JS / reduced-motion).

## Still needed before launch

1. **Real photography.** Cover photos for all 4 projects + homepage hero are done (Mostofa uploaded via `/admin`, 25 Oct). Each project's full gallery (any number of photos, featured grid + lightbox) is imported in bulk from a Drive folder via `/admin` — needs a Google Cloud API key first (Drive API enabled; see README "Setting up the Google Cloud API key"). Not yet configured as of this writing, so project pages still show the old 3-photo fallback gallery.
2. **Video**: decided — unlisted YouTube embed (Mostofa's call, 24 Oct). Not yet wired into any page; needs the actual YouTube upload + embed added to Lisa's project page once the video is up.
3. **Google Places API key**: `api/config.sample.php` has the full setup instructions. Someone needs to create a Google Cloud project, enable "Places API (New)", generate a restricted API key, and create `api/config.php` (gitignored) on the live server with that key. Until then, the reviews section falls back to the curated real quotes already in `index.html` (Lynda Kennedy, Kerry Waters, Leigh, Steve — all verified real review text from the brand asset carousel).
4. **Footer/legal details**: deliberately left out per the brief's hard rule against inventing business specifics — Woodstock's own ABN, QBCC licence number, and opening hours are not published anywhere, and the second phone number from the invoices (0413 174 951) is not published. Ask Jack via Mostofa before adding any of these.
5. **Mail deliverability**: `CONTACT_FROM_EMAIL` in `api/config.php` should be a real mailbox/alias on the woodstockcabinetmakers.com.au domain (set up in hPanel) — using a from-address PHP `mail()` can't authenticate will hurt deliverability/spam scoring.

## More real projects exist (not in this build's scope yet)

Mostofa's Drive folder shows finished photo sets for at least two more real projects beyond the 4 in this build: a **Lance & Gayle kitchen** and a **"Holland" project**, plus an unlabeled "1st project" folder that couldn't be confidently attributed to a named client without guessing. If Jack wants these added to the portfolio, confirm the client names/details and treat them the same way as the existing 4 (new project page + card in `/projects/` + homepage band + relevant service page).

## Hard rules carried over from the original brief (still apply)

- Never invent business specifics (pricing, timelines, licence numbers, awards, hours).
- Verify visually (screenshot desktop + mobile) before calling anything done.
- Nothing goes live on the real domain without Jack's sign-off.
- This is a separate business from Bespoke Woodcraft / Innovators Direct — never reuse their content or tone.
- Clients may be named (confirmed by Mostofa), but avoid exact street addresses/suburbs beyond what's already approved (Toowong is fine; nothing more specific).
