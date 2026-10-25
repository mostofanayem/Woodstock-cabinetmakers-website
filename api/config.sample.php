<?php
/**
 * Copy this file to config.php on the server (NOT in git) and fill in real values.
 * api/config.php is gitignored on purpose — never commit real API keys or secrets.
 */

return [
    // Google Cloud API key with "Places API (New)" enabled, billing enabled on the project.
    // Restrict the key in Google Cloud Console to:
    //   - API restriction: Places API (New) only
    //   - Application restriction: IP addresses -> your Hostinger server's outbound IP
    // Never use an unrestricted key, and never expose this key in any client-side code.
    'GOOGLE_PLACES_API_KEY' => '',

    // Optional: once you know the real Google Place ID for Woodstock Cabinet Makers
    // (looks like "ChIJ..."), paste it here to skip the Find Place lookup on every cache refresh.
    // Leave blank and the proxy will resolve it automatically from the business name + address below.
    'GOOGLE_PLACE_ID' => '',

    // Used only to resolve the Place ID automatically when GOOGLE_PLACE_ID is blank.
    'BUSINESS_NAME' => 'Woodstock Cabinet Makers',
    'BUSINESS_ADDRESS' => 'Unit 1/28 Bangor St, Archerfield QLD 4108, Australia',

    // How long (in seconds) to cache the Places API response before refreshing.
    // Keep this reasonably high — Places API Details calls are billed per request.
    'CACHE_SECONDS' => 21600, // 6 hours

    // Contact form delivery address.
    'CONTACT_TO_EMAIL' => 'jack@woodcab.com.au',

    // "From" address used when sending mail via PHP mail(). Using an address on your own
    // domain (rather than the visitor's email) avoids spam-filter and SPF/DKIM problems.
    // Set this up as a real mailbox or alias in hPanel once the site is live.
    'CONTACT_FROM_EMAIL' => 'noreply@woodstockcabinetmakers.com.au',
    'CONTACT_FROM_NAME' => 'Woodstock Cabinet Makers Website',

    // Password for /admin (the photo-upload tool). Generate a hash locally with:
    //   php -r "echo password_hash('your-password-here', PASSWORD_DEFAULT), PHP_EOL;"
    // and paste the result below. Never put the plain-text password here.
    'ADMIN_PASSWORD_HASH' => '',

    // Google Cloud API key with the "Google Drive API" enabled (and, optionally,
    // "Places API (New)" on the same key/project to also power live Google
    // Reviews — see GOOGLE_PLACES_API_KEY above). Used only to list the photos
    // inside a Drive folder you paste into /admin's bulk importer; the folder
    // must be shared "Anyone with the link" since this key has no personal
    // Google login behind it. Restrict the key in Google Cloud Console to:
    //   - API restriction: Google Drive API (+ Places API (New) if reused)
    //   - Application restriction: IP addresses -> your Hostinger server's outbound IP
    'GOOGLE_API_KEY' => '',
];
