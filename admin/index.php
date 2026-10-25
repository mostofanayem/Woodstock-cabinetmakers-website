<?php
/**
 * Private photo-upload tool. Not linked from anywhere on the public site.
 * Log in with the admin password (see api/config.php), drop a photo into any
 * labeled slot (or paste a Google Drive share link), and it's resized and
 * optimised and saved straight over the matching placeholder — no code
 * changes, no FTP needed.
 *
 * Drive links: the file must be shared as "Anyone with the link" — this
 * script fetches it directly from Google's servers, it cannot use anyone's
 * personal Google login. Only drive.google.com / googleusercontent.com URLs
 * are accepted (fetching arbitrary URLs server-side would be a security risk).
 */

session_start();

$configPath = __DIR__ . '/../api/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    exit('api/config.php is missing. Copy api/config.sample.php to api/config.php and set ADMIN_PASSWORD_HASH.');
}
$config = require $configPath;
$passwordHash = $config['ADMIN_PASSWORD_HASH'] ?? '';

/* ---- Slot registry: key => [destination filename, label, group] ---- */
$SLOTS = [
    'hero' => ['hero.jpg', 'Homepage hero photo', 'Homepage'],

    'lisas-kitchen-main' => ['lisas-kitchen-main.jpg', 'Main photo', "Lisa's Kitchen"],
    'lisas-kitchen-wide' => ['lisas-kitchen-wide.jpg', 'Gallery — wide shot', "Lisa's Kitchen"],
    'lisas-kitchen-detail-1' => ['lisas-kitchen-detail-1.jpg', 'Gallery — detail 1', "Lisa's Kitchen"],
    'lisas-kitchen-detail-2' => ['lisas-kitchen-detail-2.jpg', 'Gallery — detail 2 (joinery)', "Lisa's Kitchen"],

    'maries-fireplace-main' => ['maries-fireplace-main.jpg', 'Main photo', "Marie's Fireplace & Cabinetry"],
    'maries-fireplace-wide' => ['maries-fireplace-wide.jpg', 'Gallery — wide shot', "Marie's Fireplace & Cabinetry"],
    'maries-fireplace-detail-1' => ['maries-fireplace-detail-1.jpg', 'Gallery — detail 1', "Marie's Fireplace & Cabinetry"],
    'maries-fireplace-detail-2' => ['maries-fireplace-detail-2.jpg', 'Gallery — detail 2 (joinery)', "Marie's Fireplace & Cabinetry"],

    'debbies-project-main' => ['debbies-project-main.jpg', 'Main photo', "Debbie's Entertainment Wall, Kitchen & Wardrobe"],
    'debbies-project-wide' => ['debbies-project-wide.jpg', 'Gallery — wide shot', "Debbie's Entertainment Wall, Kitchen & Wardrobe"],
    'debbies-kitchen-detail' => ['debbies-kitchen-detail.jpg', 'Gallery — kitchen detail', "Debbie's Entertainment Wall, Kitchen & Wardrobe"],
    'debbies-wardrobe-detail' => ['debbies-wardrobe-detail.jpg', 'Gallery — wardrobe detail', "Debbie's Entertainment Wall, Kitchen & Wardrobe"],

    'jennifers-reno-main' => ['jennifers-reno-main.jpg', 'Main photo', "Jennifer's Queenslander Renovation"],
    'jennifers-reno-wide' => ['jennifers-reno-wide.jpg', 'Gallery — wide shot', "Jennifer's Queenslander Renovation"],
    'jennifers-wardrobe' => ['jennifers-wardrobe.jpg', 'Gallery — walk-in wardrobe', "Jennifer's Queenslander Renovation"],
    'jennifers-laundry' => ['jennifers-laundry.jpg', 'Gallery — laundry', "Jennifer's Queenslander Renovation"],
];

$PHOTOS_DIR = __DIR__ . '/../assets/img/photos';
$MAX_BYTES = 25 * 1024 * 1024; // 25MB per upload, well above a typical phone photo
$MAX_EDGE = 2400; // longest side, matches the brief's web-size guidance

/* ---- Tiny login-attempt throttle ---- */
function tooManyAttempts(): bool
{
    $fails = $_SESSION['login_fails'] ?? 0;
    $lastFail = $_SESSION['last_fail'] ?? 0;
    if ($fails >= 6 && (time() - $lastFail) < 300) {
        return true;
    }
    if ((time() - $lastFail) > 300) {
        $_SESSION['login_fails'] = 0;
    }
    return false;
}

/* ---- Auth ---- */
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ./');
    exit;
}

if (isset($_POST['password']) && !isset($_SESSION['authed'])) {
    if (tooManyAttempts()) {
        $loginError = 'Too many attempts. Try again in a few minutes.';
    } elseif ($passwordHash === '') {
        $loginError = 'Admin password is not set up yet (ADMIN_PASSWORD_HASH is blank in api/config.php).';
    } elseif (password_verify($_POST['password'], $passwordHash)) {
        $_SESSION['authed'] = true;
        $_SESSION['login_fails'] = 0;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    } else {
        $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
        $_SESSION['last_fail'] = time();
        $loginError = 'Wrong password.';
    }
}

$isAuthed = isset($_SESSION['authed']) && $_SESSION['authed'] === true;

/* ---- Handle photo upload ---- */
$results = [];
if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_submit'])) {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        $results[] = ['ok' => false, 'label' => 'Form', 'message' => 'Form expired — please try again.'];
    } else {
        if (!is_dir($PHOTOS_DIR)) {
            @mkdir($PHOTOS_DIR, 0755, true);
        }
        foreach ($SLOTS as $key => [$filename, $label, $group]) {
            $hasFile = isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
            $driveUrl = trim($_POST[$key . '_url'] ?? '');

            if (!$hasFile && $driveUrl === '') {
                continue; // slot left empty — nothing to do
            }

            $destination = $PHOTOS_DIR . '/' . $filename;

            if ($hasFile) {
                $file = $_FILES[$key];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $results[] = ['ok' => false, 'label' => $label, 'message' => 'Upload failed (error code ' . $file['error'] . ').'];
                    continue;
                }
                if ($file['size'] > $MAX_BYTES) {
                    $results[] = ['ok' => false, 'label' => $label, 'message' => 'File is larger than 25MB.'];
                    continue;
                }
                $tmpPath = $file['tmp_name'];
                $sourceDesc = 'upload';
            } else {
                $fetch = fetchFromDriveLink($driveUrl, $MAX_BYTES);
                if ($fetch['error']) {
                    $results[] = ['ok' => false, 'label' => $label, 'message' => $fetch['error']];
                    continue;
                }
                $tmpPath = $fetch['tmp_path'];
                $sourceDesc = 'Drive link';
            }

            $info = @getimagesize($tmpPath);
            if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $results[] = ['ok' => false, 'label' => $label, 'message' => "Not a JPEG, PNG or WebP image ($sourceDesc)."];
                if (!$hasFile) @unlink($tmpPath);
                continue;
            }
            $ok = processAndSaveImage($tmpPath, $info['mime'], $destination, $MAX_EDGE);
            if (!$hasFile) @unlink($tmpPath);
            $results[] = [
                'ok' => $ok,
                'label' => $label,
                'message' => $ok ? "Updated from $sourceDesc." : 'Could not process this image on the server.',
            ];
        }
        if (!$results) {
            $results[] = ['ok' => false, 'label' => 'Form', 'message' => 'No files or links were given.'];
        }
    }
}

/**
 * Pull a Google Drive file ID out of any of the common share-link shapes:
 *   https://drive.google.com/file/d/FILE_ID/view?usp=sharing
 *   https://drive.google.com/open?id=FILE_ID
 *   https://drive.google.com/uc?id=FILE_ID&export=download
 *   https://lh3.googleusercontent.com/d/FILE_ID
 * Returns null if nothing recognisable was found.
 */
function extractDriveFileId(string $url): ?string
{
    if (preg_match('#/d/([a-zA-Z0-9_-]{10,})#', $url, $m)) {
        return $m[1];
    }
    if (preg_match('#[?&]id=([a-zA-Z0-9_-]{10,})#', $url, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Downloads a Google Drive-hosted image straight from Google's own servers
 * into a temp file. Only accepts drive.google.com / googleusercontent.com
 * URLs (never an arbitrary caller-supplied host — this runs server-side on
 * a logged-in request, so an open URL fetcher here would be an SSRF risk).
 * The source file must be shared as "Anyone with the link"; this request
 * carries no Google login of its own.
 *
 * @return array{tmp_path: ?string, error: ?string}
 */
function fetchFromDriveLink(string $url, int $maxBytes): array
{
    $host = parse_url($url, PHP_URL_HOST);
    $allowedHosts = ['drive.google.com', 'docs.google.com', 'lh3.googleusercontent.com', 'googleusercontent.com'];
    $hostOk = $host && array_filter($allowedHosts, fn($h) => $host === $h || str_ends_with($host, '.' . $h));
    if (!$host || !$hostOk) {
        return ['tmp_path' => null, 'error' => 'That link needs to be a drive.google.com (or googleusercontent.com) URL.'];
    }

    $fileId = extractDriveFileId($url);
    if (!$fileId) {
        return ['tmp_path' => null, 'error' => "Couldn't find a file ID in that link — paste the normal 'Share' link from Drive."];
    }

    // Google's image-serving host — gives the original resolution, works for
    // anything shared "Anyone with the link", and skips the virus-scan
    // interstitial that drive.google.com/uc shows for larger files.
    $fetchUrl = "https://lh3.googleusercontent.com/d/{$fileId}=s0";

    $tmpPath = sys_get_temp_dir() . '/drive-fetch-' . bin2hex(random_bytes(8));
    $fh = fopen($tmpPath, 'wb');
    if (!$fh) {
        return ['tmp_path' => null, 'error' => 'Could not create a temp file on the server.'];
    }

    $tooBig = false;
    $ch = curl_init($fetchUrl);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WoodstockPhotoUpload/1.0)',
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => function ($res, $expectedDown, $downloaded) use ($maxBytes, &$tooBig) {
            if ($downloaded > $maxBytes) {
                $tooBig = true;
                return 1; // non-zero aborts the transfer
            }
            return 0;
        },
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    fclose($fh);

    if ($tooBig) {
        @unlink($tmpPath);
        return ['tmp_path' => null, 'error' => 'That file is larger than 25MB.'];
    }
    if ($curlError || $httpCode !== 200 || filesize($tmpPath) === 0) {
        @unlink($tmpPath);
        return ['tmp_path' => null, 'error' => "Couldn't fetch that file (HTTP $httpCode). Make sure it's shared as \"Anyone with the link\"."];
    }

    return ['tmp_path' => $tmpPath, 'error' => null];
}

/**
 * Resize (if needed) and re-encode an uploaded image as JPEG, auto-orienting
 * from EXIF where available. Tries Imagick first, falls back to GD, falls
 * back to a plain copy if neither extension is available.
 */
function processAndSaveImage(string $tmpPath, string $mime, string $destination, int $maxEdge): bool
{
    $tmpOut = $destination . '.tmp-' . bin2hex(random_bytes(4));

    if (extension_loaded('imagick')) {
        try {
            $img = new Imagick($tmpPath);
            $img->autoOrient();
            $img->stripImage();
            $w = $img->getImageWidth();
            $h = $img->getImageHeight();
            if (max($w, $h) > $maxEdge) {
                $img->resizeImage($w >= $h ? $maxEdge : 0, $h > $w ? $maxEdge : 0, Imagick::FILTER_LANCZOS, 1, true);
            }
            $img->setImageFormat('jpeg');
            $img->setImageCompressionQuality(82);
            $img->writeImage($tmpOut);
            $img->clear();
            return rename($tmpOut, $destination);
        } catch (\Throwable $e) {
            @unlink($tmpOut);
            // fall through to GD
        }
    }

    if (extension_loaded('gd')) {
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmpPath),
            'image/png' => @imagecreatefrompng($tmpPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false,
            default => false,
        };
        if (!$src) {
            return false;
        }

        // Correct orientation from EXIF (JPEG only).
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmpPath);
            $orientation = $exif['Orientation'] ?? 1;
            $src = match ($orientation) {
                3 => imagerotate($src, 180, 0),
                6 => imagerotate($src, -90, 0),
                8 => imagerotate($src, 90, 0),
                default => $src,
            };
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxEdge / max($w, $h));
        $newW = max(1, (int) round($w * $scale));
        $newH = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        $ok = imagejpeg($dst, $tmpOut, 82);
        imagedestroy($src);
        imagedestroy($dst);
        if ($ok) {
            return rename($tmpOut, $destination);
        }
        @unlink($tmpOut);
        return false;
    }

    // No image extension available — save the original file as-is.
    return copy($tmpPath, $destination);
}

$csrf = $_SESSION['csrf'] ?? '';
$photosUrl = '../assets/img/photos/';
?><!DOCTYPE html>
<html lang="en-AU">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Photo Upload | Woodstock Cabinet Makers</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #0b0b0a; color: #f5f1e8; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.5; }
  .wrap { max-width: 880px; margin: 0 auto; padding: 2rem 1.25rem 5rem; }
  h1 { font-size: 1.5rem; margin-bottom: 0.25rem; }
  .sub { color: #a3c2a0; font-size: 0.9rem; margin-bottom: 2rem; }
  .login-box { max-width: 360px; margin: 10vh auto; padding: 2rem; border: 1px solid #565448; border-radius: 6px; }
  input[type="password"], input[type="file"] { width: 100%; padding: 0.65rem 0.8rem; background: #161513; border: 1px solid #565448; border-radius: 4px; color: #f5f1e8; font-size: 1rem; }
  button { cursor: pointer; border: 0; border-radius: 4px; padding: 0.7rem 1.4rem; font-size: 0.95rem; font-weight: 600; background: #1f7a4a; color: #fff; }
  button:hover { background: #2f9a60; }
  button.secondary { background: transparent; border: 1px solid #565448; color: #f5f1e8; }
  .error { background: rgba(198,83,66,0.15); border: 1px solid #c65342; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
  .group { margin-bottom: 2.5rem; }
  .group h2 { font-size: 1.1rem; border-bottom: 1px solid #565448; padding-bottom: 0.5rem; margin-bottom: 1rem; color: #c4beb2; }
  .slot { display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1.25rem; flex-wrap: wrap; }
  .slot img { width: 90px; height: 68px; object-fit: cover; border-radius: 4px; border: 1px solid #565448; flex-shrink: 0; }
  .slot .field { flex: 1; min-width: 220px; }
  .slot label { display: block; font-size: 0.85rem; color: #c4beb2; margin-bottom: 0.3rem; }
  .slot .or { font-size: 0.78rem; color: #80987d; margin: 0.4rem 0 0.3rem; }
  input[type="url"] { width: 100%; padding: 0.55rem 0.7rem; background: #161513; border: 1px solid #565448; border-radius: 4px; color: #f5f1e8; font-size: 0.9rem; }
  .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
  .results { margin-bottom: 2rem; }
  .result-row { padding: 0.5rem 0.8rem; border-radius: 4px; margin-bottom: 0.4rem; font-size: 0.9rem; }
  .result-row.ok { background: rgba(31,122,74,0.18); }
  .result-row.fail { background: rgba(198,83,66,0.14); }
  .save-bar { position: sticky; bottom: 0; background: #0b0b0a; padding: 1rem 0; border-top: 1px solid #565448; }
  small.hint { color: #80987d; }
</style>
</head>
<body>

<?php if (!$isAuthed): ?>
  <div class="login-box">
    <h1>Photo Upload</h1>
    <p class="sub">Woodstock Cabinet Makers — admin only</p>
    <?php if (!empty($loginError)): ?><div class="error"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
    <form method="post">
      <input type="password" name="password" placeholder="Password" autofocus required>
      <div style="margin-top:1rem;"><button type="submit">Log In</button></div>
    </form>
  </div>
<?php else: ?>
  <div class="wrap">
    <div class="topbar">
      <div>
        <h1>Photo Upload</h1>
        <p class="sub">Drop a photo into any slot below and press Save. Only the slots you fill in get changed.</p>
      </div>
      <form method="post"><button type="submit" name="logout" value="1" class="secondary">Log Out</button></form>
    </div>

    <?php if ($results): ?>
      <div class="results">
        <?php foreach ($results as $r): ?>
          <div class="result-row <?= $r['ok'] ? 'ok' : 'fail' ?>"><strong><?= htmlspecialchars($r['label']) ?>:</strong> <?= htmlspecialchars($r['message']) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <?php
      $currentGroup = null;
      foreach ($SLOTS as $key => [$filename, $label, $group]):
          if ($group !== $currentGroup):
              if ($currentGroup !== null) echo '</div>';
              $currentGroup = $group;
              echo '<div class="group"><h2>' . htmlspecialchars($group) . '</h2>';
          endif;
          $thumbSrc = $photosUrl . $filename . '?v=' . @filemtime($PHOTOS_DIR . '/' . $filename);
      ?>
        <div class="slot">
          <img src="<?= htmlspecialchars($thumbSrc) ?>" alt="">
          <div class="field">
            <label for="<?= $key ?>"><?= htmlspecialchars($label) ?></label>
            <input type="file" id="<?= $key ?>" name="<?= $key ?>" accept="image/jpeg,image/png,image/webp">
            <p class="or">or paste a Google Drive share link ("Anyone with the link")</p>
            <input type="url" id="<?= $key ?>_url" name="<?= $key ?>_url" placeholder="https://drive.google.com/file/d/...">
          </div>
        </div>
      <?php endforeach; ?>
      </div>
      <div class="save-bar">
        <button type="submit" name="upload_submit" value="1">Save Photos</button>
        <small class="hint" style="margin-left:1rem;">JPEG, PNG or WebP, up to 25MB — upload a file or paste a Drive link, not both.</small>
      </div>
    </form>
  </div>
<?php endif; ?>

</body>
</html>
