<?php
/**
 * The web app manifest.
 *
 * Served from PHP rather than shipped as a static manifest.json for one reason:
 * start_url and scope have to be absolute paths that know where the app is
 * installed. APP_BASE is '/eq' here and would be '' on a real domain, and a
 * static file cannot say both — a wrong start_url is not a small mistake, it is
 * an app that opens the wrong thing every time it is launched from the home
 * screen.
 *
 * The two icon files are SVG. See the note in assets/icons/icon.svg for why, and
 * for the one platform that does not accept them.
 */

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/manifest+json; charset=utf-8');
// The manifest can be read by anyone but is not a page and has nothing to say to
// a search result.
header('X-Robots-Tag: noindex');

$base = APP_BASE;
$siteName = setting('site.name', t('site.name'));

$manifest = [
    'name'        => $siteName,
    // The label under the icon on a home screen. Not truncated here: the
    // platform shortens what does not fit, and it does that at a character
    // boundary in the reader's own language rather than wherever a byte count
    // happened to land.
    'short_name'  => $siteName,
    'description' => setting('seo.default_desc') !== '' ? setting('seo.default_desc') : t('hero.sub'),
    'lang'        => current_lang(),
    'dir'         => html_dir(),

    'start_url'   => $base . '/index.php',
    'scope'       => $base . '/',
    'id'          => $base . '/',

    // Standalone rather than fullscreen: the map needs the top of the screen for
    // the header and the share panel, and a phone with no status bar at all is
    // nobody's favourite.
    'display'     => 'standalone',
    'orientation' => 'any',

    // The splash screen and the first frame while the page paints. The light
    // surface, because the theme is a per-browser choice the manifest has no way
    // to read — a dark-mode visitor sees the light colour for one frame and then
    // the page they chose.
    'background_color' => '#fbfaf9',
    'theme_color'      => '#8b1538',

    'icons' => [
        [
            'src'     => $base . '/assets/icons/icon.svg',
            'sizes'   => 'any',
            'type'    => 'image/svg+xml',
            'purpose' => 'any',
        ],
        [
            'src'     => $base . '/assets/icons/icon-maskable.svg',
            'sizes'   => 'any',
            'type'    => 'image/svg+xml',
            'purpose' => 'maskable',
        ],
    ],

    // Long-press shortcuts on the launcher. No icons: the platform draws a
    // monogram from the label rather than showing a broken image.
    'shortcuts' => [
        [
            'name' => t('nav.places'),
            'url'  => $base . '/places.php',
        ],
        [
            'name' => t('nav.trip'),
            'url'  => $base . '/trip.php',
        ],
    ],

    'categories' => ['travel', 'lifestyle', 'education'],
];

// Plain JSON, not the HEX_-escaped flavour: this is served as a manifest and
// never written into an HTML block, so there is nothing here for a stray
// </script> to break out of. Pretty-printed because a manifest is read far more
// often by somebody debugging an install prompt than by a browser.
echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
