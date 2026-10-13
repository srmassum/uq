<?php
/**
 * Site settings — the key/value rows the Settings screen writes.
 *
 * Deliberately not a config class. The values here are the ones an editor should
 * be able to change without a deploy (the tagline, the footer disclaimer, whether
 * the site is in maintenance mode); anything that changes how the code behaves
 * belongs in inc/config.php where it is version-controlled.
 *
 * Reads degrade to the supplied default, so a fresh install with no settings
 * table renders the built-in copy instead of blanking the header.
 */

declare(strict_types=1);

/** The keys the panel offers, with their shipped defaults. */
function setting_defaults(): array
{
    return [
        'site.name'          => SITE_NAME,
        'site.tagline'       => SITE_TAGLINE,
        'site.default_lang'  => DEFAULT_LANG,
        'footer.disclaimer'  => '',
        'contact.email'      => '',
        'social.instagram'   => '',
        'social.x'           => '',
        'social.tiktok'      => '',
        'seo.default_title'  => '',
        'seo.default_desc'   => '',
        'map.default_zoom'   => 'fit',
        'maintenance'        => '0',
        'maintenance_note'   => '',
        'analytics.enabled'  => '1',
    ];
}

/** Every setting, defaults filled in. Cached for the request. */
function settings_all(): array
{
    static $all = null;
    if ($all !== null) {
        return $all;
    }

    $all = setting_defaults();
    try {
        foreach (qAll('SELECT k, v FROM settings') as $row) {
            $all[(string) $row['k']] = (string) ($row['v'] ?? '');
        }
    } catch (Throwable $e) {
        // No table yet: the defaults are a perfectly good site.
        error_log('settings_all: ' . $e->getMessage());
    }
    return $all;
}

function setting(string $key, ?string $default = null): string
{
    $all = settings_all();
    $v = $all[$key] ?? null;
    if ($v === null || $v === '') {
        return $default ?? (string) (setting_defaults()[$key] ?? '');
    }
    return (string) $v;
}

function setting_on(string $key): bool
{
    return setting($key) === '1';
}

function setting_set(string $key, string $value): void
{
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)',
      [mb_substr($key, 0, 64), $value]);
}

/** True when the site is closed to the public. The panel ignores this. */
function maintenance_mode(): bool
{
    return setting_on('maintenance');
}

/**
 * Close the public site when the maintenance switch is on.
 *
 * Called from page_head(), which every public page goes through, so there is
 * one place to reason about rather than nine. The panel does not use page_head,
 * so staff keep working while the doors are shut — which is the entire point of
 * the switch.
 *
 * 503 rather than 200-with-an-apology: a search engine that gets a 200 will
 * index the notice and may keep showing it after the site is back. Retry-After
 * says roughly when to come back.
 *
 * Note what is *not* closed: img.php still serves saved card images, so a card
 * already shared on Facebook keeps rendering its preview while the site is
 * down. Losing the preview on an existing post is the one outage nobody would
 * forgive.
 */
function maintenance_guard(): void
{
    if (!maintenance_mode()) {
        return;
    }

    $note     = setting('maintenance_note');
    $siteName = setting('site.name', SITE_NAME);

    // bootstrap.php opens an output buffer; throw away anything a page managed
    // to print before reaching its header, then send the real status.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(503);
    header('Retry-After: 3600');
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');

    // Deliberately self-contained: no stylesheet, no fonts, no JavaScript. The
    // reason the site is closed might be that the assets are being rebuilt.
    ?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($siteName) ?> is having a moment</title>
<style>
  :root { color-scheme: light dark; }
  body {
    margin: 0; min-height: 100vh; display: grid; place-items: center;
    background: #fbf7f2; color: #2a1f1a; padding: 24px;
    font: 17px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  }
  .box { max-width: 34rem; text-align: center; }
  .mark {
    width: 56px; height: 56px; border-radius: 14px; background: #8b1538;
    margin: 0 auto 20px; display: grid; place-items: center;
    color: #fff; font-weight: 800; font-size: 26px;
  }
  h1 { font-size: 1.5rem; margin: 0 0 10px; letter-spacing: -0.01em; }
  p { margin: 0 0 12px; color: #5d4a42; }
  .note {
    background: #fff; border: 1px solid #e7ddd3; border-radius: 14px;
    padding: 16px 18px; text-align: start; white-space: pre-wrap;
  }
  @media (prefers-color-scheme: dark) {
    body { background: #14100e; color: #f2ece7; }
    p { color: #b8a89f; }
    .note { background: #1d1815; border-color: #322823; }
  }
</style>
</head>
<body>
  <div class="box">
    <div class="mark" aria-hidden="true">Q</div>
    <h1>Back in a little while</h1>
    <p><?= e($siteName) ?> is being worked on right now. Maps that have already been shared keep working — try again shortly.</p>
    <?php if ($note !== ''): ?>
      <div class="note"><?= e($note) ?></div>
    <?php endif; ?>
  </div>
</body>
</html>
    <?php
    exit;
}
