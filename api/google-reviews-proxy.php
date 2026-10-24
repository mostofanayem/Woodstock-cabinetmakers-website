<?php
/**
 * Server-side proxy for Google Places API (New) reviews.
 *
 * Keeps the API key off the client, resolves the Place ID once (then caches it),
 * and caches the review response for CACHE_SECONDS to control API cost and quota.
 * Always returns HTTP 200 with {"ok": false} on any failure so the homepage's
 * curated fallback reviews (already in the page HTML) are used instead of a broken UI.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

function fail(): void
{
    echo json_encode(['ok' => false]);
    exit;
}

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    error_log('Woodstock reviews proxy: api/config.php is missing — copy config.sample.php and fill it in.');
    fail();
}
$config = require $configPath;

$apiKey = $config['GOOGLE_PLACES_API_KEY'] ?? '';
if ($apiKey === '') {
    fail(); // Not configured yet — curated fallback reviews in the page will show instead.
}

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . '/google-reviews.json';
$cacheSeconds = (int) ($config['CACHE_SECONDS'] ?? 21600);

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheSeconds) {
    $cached = file_get_contents($cacheFile);
    if ($cached !== false) {
        echo $cached;
        exit;
    }
}

function placesApiRequest(string $url, string $apiKey, string $fieldMask, ?array $jsonBody = null)
{
    $ch = curl_init($url);
    $headers = [
        'X-Goog-Api-Key: ' . $apiKey,
        'X-Goog-FieldMask: ' . $fieldMask,
        'Content-Type: application/json',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 8,
    ]);
    if ($jsonBody !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody));
    }
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $status < 200 || $status >= 300) {
        return null;
    }
    return json_decode($response, true);
}

// Resolve Place ID — prefer the one pinned in config.php to save an API call.
$placeId = trim($config['GOOGLE_PLACE_ID'] ?? '');
if ($placeId === '') {
    $placeIdCacheFile = $cacheDir . '/place-id.txt';
    if (is_file($placeIdCacheFile)) {
        $placeId = trim(file_get_contents($placeIdCacheFile));
    }
}
if ($placeId === '') {
    $businessName = $config['BUSINESS_NAME'] ?? 'Woodstock Cabinet Makers';
    $businessAddress = $config['BUSINESS_ADDRESS'] ?? '';
    $searchResult = placesApiRequest(
        'https://places.googleapis.com/v1/places:searchText',
        $apiKey,
        'places.id,places.displayName',
        ['textQuery' => trim($businessName . ', ' . $businessAddress)]
    );
    $placeId = $searchResult['places'][0]['id'] ?? '';
    if ($placeId === '') {
        fail();
    }
    @file_put_contents($cacheDir . '/place-id.txt', $placeId);
}

$details = placesApiRequest(
    'https://places.googleapis.com/v1/places/' . rawurlencode($placeId),
    $apiKey,
    'id,displayName,rating,userRatingCount,reviews',
    null
);

if (!$details || !isset($details['rating'])) {
    fail();
}

$reviews = [];
foreach (($details['reviews'] ?? []) as $review) {
    $reviews[] = [
        'author' => $review['authorAttribution']['displayName'] ?? 'Google user',
        'rating' => $review['rating'] ?? 5,
        'text' => $review['text']['text'] ?? ($review['originalText']['text'] ?? ''),
        'relativeTime' => $review['relativePublishTimeDescription'] ?? '',
    ];
}

$payload = json_encode([
    'ok' => true,
    'rating' => $details['rating'],
    'total' => $details['userRatingCount'] ?? count($reviews),
    'reviews' => $reviews,
]);

@file_put_contents($cacheFile, $payload);

echo $payload;
