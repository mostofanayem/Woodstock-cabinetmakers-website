<?php
/**
 * Contact form handler — receives the homepage contact form and the quick-chat
 * widget, validates/sanitises input, and emails Jack via PHP mail() (works on
 * standard Hostinger shared hosting with no extra setup).
 */

header('Content-Type: application/json; charset=utf-8');

function respond(bool $ok, string $error = ''): void
{
    http_response_code($ok ? 200 : 422);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed');
}

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    error_log('Woodstock contact form: api/config.php is missing — copy config.sample.php and fill it in.');
    respond(false, 'Form is not configured yet');
}
$config = require $configPath;

// Honeypot: real visitors never fill this hidden field in.
$honeypot = trim($_POST['website'] ?? '');
if ($honeypot !== '') {
    // Pretend success so bots don't learn the honeypot was detected.
    respond(true);
}

function cleanText(string $value, int $maxLength = 2000): string
{
    $value = trim($value);
    $value = preg_replace('/[\r\n]+/', ' ', $value); // strip header-injection newlines
    $value = strip_tags($value);
    return mb_substr($value, 0, $maxLength);
}

$name = cleanText($_POST['name'] ?? '', 150);
$email = trim($_POST['email'] ?? '');
$phone = cleanText($_POST['phone'] ?? '', 40);
$suburb = cleanText($_POST['suburb'] ?? '', 100);
$budget = cleanText($_POST['budget'] ?? '', 40);
$message = trim($_POST['message'] ?? '');
$source = cleanText($_POST['source'] ?? 'Website', 40);

if ($name === '' || $message === '') {
    respond(false, 'Please fill in your name and message.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.');
}
$message = mb_substr(strip_tags($message), 0, 5000);

$to = $config['CONTACT_TO_EMAIL'];
$fromEmail = $config['CONTACT_FROM_EMAIL'];
$fromName = $config['CONTACT_FROM_NAME'];

$subject = '=?UTF-8?B?' . base64_encode("New enquiry ({$source}) — {$name}") . '?=';

$lines = [
    "New enquiry from woodstockcabinetmakers.com.au",
    "Source: {$source}",
    "",
    "Name: {$name}",
    "Email: {$email}",
];
if ($phone !== '') $lines[] = "Phone: {$phone}";
if ($suburb !== '') $lines[] = "Suburb: {$suburb}";
if ($budget !== '') $lines[] = "Approx. budget: {$budget}";
$lines[] = "";
$lines[] = "Message:";
$lines[] = $message;

$body = implode("\n", $lines);

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = sprintf('From: =?UTF-8?B?%s?= <%s>', base64_encode($fromName), $fromEmail);
$headers[] = sprintf('Reply-To: %s', $email);

$sent = @mail($to, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    error_log('Woodstock contact form: mail() returned false.');
    respond(false, 'Could not send message right now.');
}

respond(true);
