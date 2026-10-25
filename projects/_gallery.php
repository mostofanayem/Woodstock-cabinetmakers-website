<?php
/**
 * Renders a project's photo gallery. Looks for imported photos first
 * (assets/img/photos/gallery/{slug}/01.jpg, 02.jpg, ...  — populated by the
 * /admin Drive-folder importer, in import order) and falls back to the
 * original 3 fixed placeholder shots if nothing has been imported yet.
 *
 * The first two photos are shown larger ("featured"); the rest fill in at
 * normal size. Every tile opens the full photo in the on-page lightbox
 * (assets/js/main.js handles the click behaviour for any .gallery-grid).
 */
function render_project_gallery(string $slug, array $fallbackImages): void
{
    $galleryDir = __DIR__ . '/../assets/img/photos/gallery/' . $slug;
    $webBase = '../assets/img/photos/gallery/' . $slug . '/';

    $images = [];
    if (is_dir($galleryDir)) {
        $files = glob($galleryDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
        natsort($files);
        foreach ($files as $path) {
            $images[] = ['src' => $webBase . basename($path), 'alt' => ''];
        }
    }

    if (!$images) {
        $images = $fallbackImages;
    }
    if (!$images) {
        return;
    }

    echo '<div class="gallery-grid" data-lightbox-group="' . htmlspecialchars($slug) . '">';
    foreach ($images as $i => $img) {
        $featured = $i < 2 ? ' gallery-grid__item--featured' : '';
        echo '<button type="button" class="gallery-grid__item' . $featured . '" data-full="' . htmlspecialchars($img['src']) . '" aria-label="View photo full screen">';
        echo '<img src="' . htmlspecialchars($img['src']) . '" alt="' . htmlspecialchars($img['alt']) . '" loading="lazy">';
        echo '</button>';
    }
    echo '</div>';
}
