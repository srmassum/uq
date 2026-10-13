<?php
/**
 * Shared page chrome. Two functions: page_head() and page_foot().
 *
 * The copy here is read through setting() with the language file as the
 * fallback, so an editor can change the site's name, its tagline, the footer
 * disclaimer and the social links from the panel without a deploy. That means
 * every public page now reads the settings table once per request — a
 * deliberate trade: the map page used to render without touching the database,
 * and the maintenance switch below is worth more than that millisecond.
 */

declare(strict_types=1);

/**
 * @param array{
 *   title?: string, desc?: string, og_image?: string, og_url?: string,
 *   body_class?: string, head?: string, noindex?: bool, canonical?: string,
 *   nav?: 'map'|'places'|'plans'|'guide'|'trip'
 * } $opts
 */
function page_head(array $opts = []): void
{
    // Before anything is printed: if the site is closed, this is where it stops.
    maintenance_guard();

    $lang = current_lang();
    $dir  = html_dir();

    $siteName = setting('site.name', t('site.name'));

    // A blank setting means "not set", not "print nothing" — otherwise an
    // editor who clears the tagline gets an untitled browser tab.
    $defTitle = setting('seo.default_title') !== '' ? setting('seo.default_title') : t('site.tagline');
    $defDesc  = setting('seo.default_desc')  !== '' ? setting('seo.default_desc')  : t('hero.sub');

    // The site name is only appended when the page has a title of its own; on
    // the home page "Qatar Unseen" on its own is the whole title.
    $title     = isset($opts['title']) && $opts['title'] !== '' ? $opts['title'] : $defTitle;
    $isDefault = $title === $defTitle;

    $desc    = $opts['desc'] ?? $defDesc;
    $ogImage = $opts['og_image'] ?? '';
    $ogUrl   = $opts['og_url'] ?? (site_origin() . ($_SERVER['REQUEST_URI'] ?? '/'));
    $bodyCls = $opts['body_class'] ?? '';
    $full    = $isDefault ? $title : $title . ' · ' . $siteName;

    // The page's own address, with the language taken out of it. A page that
    // does not say otherwise is canonical at the address it was asked for —
    // including any filters, because on this site a filtered list of places is
    // real content rather than a duplicate of the unfiltered one.
    $rel = $opts['canonical'] ?? '';
    if ($rel === '') {
        $q = $_GET;
        unset($q['lang']);
        $rel = current_path() . ($q === [] ? '' : '?' . http_build_query($q));
    }

    // Which language is the one a crawler with no cookie would get. lang_url()
    // writes the language in explicitly for every variant, so this only decides
    // what x-default points at.
    $pref     = setting('site.default_lang');
    $xDefault = in_array($pref, SUPPORTED_LANGS, true) ? $pref : DEFAULT_LANG;
    ?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<?php // The two colours the browser paints its own chrome with, chosen by the
      // device rather than by the site — which is right up until somebody picks
      // a theme the device disagrees with. theme_boot() rewrites them below when
      // that happens. ?>
<meta name="theme-color" content="#fbfaf9" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0c0a09" media="(prefers-color-scheme: dark)">
<?= theme_boot() ?>

<title><?= e($full) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if (!empty($opts['noindex'])): ?>
<meta name="robots" content="noindex">
<?php else: ?>
<?php // Every variant of the page, in every language, each at its own URL. Two
      // pages that differ only by a cookie are one page as far as a search
      // engine is concerned, so this is the only way the Arabic copy gets read
      // at all — and the canonical is what stops the bare URL, the one people
      // actually paste, from competing with it.
      ?>
<link rel="canonical" href="<?= e(lang_url($lang, $rel)) ?>">
<?php foreach (SUPPORTED_LANGS as $code): ?>
<link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e(lang_url($code, $rel)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= e(lang_url($xDefault, $rel)) ?>">
<?php endif; ?>

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($ogUrl) ?>">
<meta property="og:locale" content="<?= $lang === 'ar' ? 'ar_QA' : 'en_QA' ?>">
<?php if ($ogImage !== ''): ?>
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:image:width" content="1080">
<meta property="og:image:height" content="1080">
<meta property="og:image:alt" content="<?= e($title) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($desc) ?>">

<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="7" fill="#8b1538"/><path d="M9 22V10h4.6c3.1 0 5 1.6 5 4.3 0 2-1 3.4-2.7 4l3.1 3.7h-3l-2.6-3.3h-1.5V22H9zm2.9-5.7h1.6c1.4 0 2.2-.7 2.2-1.9s-.8-1.9-2.2-1.9h-1.6v3.8z" fill="#fff"/></svg>') ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Kufi+Arabic:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">

<?php // Installing the site to a home screen.
      //
      // The manifest is served by PHP so that start_url and scope can be built
      // from APP_BASE — a static file cannot know whether this is a domain root
      // or a subdirectory, and getting that wrong means the installed app opens
      // the wrong thing every time it is launched.
      //
      // The two apple-* metas are for iOS, which reads neither the manifest nor
      // the service worker and needs to be told separately that this is an app
      // and what to call it. ?>
<link rel="manifest" href="<?= e(APP_BASE) ?>/manifest.php">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e($siteName) ?>">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="<?= e($siteName) ?>">
<link rel="apple-touch-icon" href="<?= e(APP_BASE) ?>/assets/icons/icon.svg">
<?= $opts['head'] ?? '' ?>
</head>
<body class="<?= e($bodyCls) ?>" data-lang="<?= e($lang) ?>" data-base="<?= e(APP_BASE) ?>">

<header class="topbar">
  <a class="brand" href="<?= e(APP_BASE) ?>/index.php">
    <span class="brand-mark" aria-hidden="true"></span>
    <span class="brand-name"><?= e($siteName) ?></span>
  </a>

  <nav class="topnav" aria-label="Main">
    <a href="<?= e(APP_BASE) ?>/index.php"<?= ($opts['nav'] ?? '') === 'map' ? ' aria-current="page"' : '' ?>><?= e(t('nav.map')) ?></a>
    <a href="<?= e(APP_BASE) ?>/places.php"<?= ($opts['nav'] ?? '') === 'places' ? ' aria-current="page"' : '' ?>><?= e(t('nav.places')) ?></a>
    <a href="<?= e(APP_BASE) ?>/itineraries.php"<?= ($opts['nav'] ?? '') === 'plans' ? ' aria-current="page"' : '' ?>><?= e(t('nav.plans')) ?></a>
    <a href="<?= e(APP_BASE) ?>/guide.php"<?= ($opts['nav'] ?? '') === 'guide' ? ' aria-current="page"' : '' ?>><?= e(t('nav.guide')) ?></a>
    <?php // The count is filled in by trip.js from localStorage — the server has
          // no idea what is on somebody's trip, so it renders the link empty and
          // the script either fills the badge or leaves it hidden. ?>
    <a href="<?= e(APP_BASE) ?>/trip.php"<?= ($opts['nav'] ?? '') === 'trip' ? ' aria-current="page"' : '' ?>><?= e(t('nav.trip')) ?><span class="nav-count" data-trip-count hidden>0</span></a>
  </nav>

  <div class="langsw" role="group" aria-label="Language">
    <?php foreach (SUPPORTED_LANGS as $code): ?>
      <a href="<?= e(lang_switch_url($code)) ?>"
         class="<?= $code === $lang ? 'on' : '' ?>"
         hreflang="<?= e($code) ?>"
         <?= $code === $lang ? 'aria-current="true"' : '' ?>><?= $code === 'ar' ? 'العربية' : 'English' ?></a>
    <?php endforeach; ?>
  </div>

  <?php // Hidden until theme.js has run: without JavaScript the button cannot do
        // anything, and a control that does nothing is worse than no control. The
        // icons are both in the markup and CSS shows the one that matches — which
        // is what the script sets data-mode to, so the two can never disagree.
        //
        // The label describes what pressing it will do rather than what the
        // theme currently is, and the icon shows the same thing, because "the
        // moon means dark" is the reading everybody already has. ?>
  <button class="theme-btn" type="button" data-theme-toggle hidden>
    <span class="theme-ico moon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
           stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
      </svg>
    </span>
    <span class="theme-ico sun" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
           stroke-width="2" stroke-linecap="round">
        <circle cx="12" cy="12" r="4.2"/>
        <path d="M12 2.4v2.2M12 19.4v2.2M4.8 4.8l1.6 1.6M17.6 17.6l1.6 1.6M2.4 12h2.2M19.4 12h2.2M4.8 19.2l1.6-1.6M17.6 6.4l1.6-1.6"/>
      </svg>
    </span>
  </button>
</header>

<main id="main">
    <?php
}

function page_foot(array $opts = []): void
{
    $disclaimer = setting('footer.disclaimer', t('footer.disclaimer'));
    $contact    = setting('contact.email');

    // Only the networks an editor has actually filled in. A row of dead icons
    // pointing at "#" is worse than no row at all.
    $social = [];
    foreach (['instagram' => 'Instagram', 'x' => 'X', 'tiktok' => 'TikTok'] as $key => $label) {
        $url = setting('social.' . $key);
        if ($url !== '' && preg_match('#^https?://#i', $url)) {
            $social[$label] = $url;
        }
    }
    ?>
</main>

<footer class="foot">
  <div class="foot-in">
    <p class="foot-note"><?= e($disclaimer) ?></p>

    <?php if ($social !== [] || $contact !== ''): ?>
      <p class="foot-links">
        <?php foreach ($social as $label => $url): ?>
          <a href="<?= e($url) ?>" rel="noopener me" target="_blank"><?= e($label) ?></a>
        <?php endforeach; ?>
        <?php if ($contact !== '' && filter_var($contact, FILTER_VALIDATE_EMAIL)): ?>
          <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <p class="foot-meta">
      <span><?= e(t('footer.made')) ?></span>
      <span aria-hidden="true">·</span>
      <span><?= e(t('footer.credit')) ?></span>
    </p>
  </div>
</footer>

<?= trip_scripts() ?>
<?= theme_scripts() ?>
<?= pwa_script() ?>
<?= $opts['scripts'] ?? '' ?>
</body>
</html>
    <?php
}

/**
 * Register the service worker.
 *
 * Inline rather than its own file, because it is four lines and a fourth
 * <script> tag on every page would cost more than it saves.
 *
 * Registered on load so that fetching and installing the worker never competes
 * with the page the visitor is actually waiting for.
 *
 * A browser without service workers — an old one, or a private window — simply
 * never runs this, which is the correct outcome and needs no fallback. Same for
 * a context that is not secure: service workers need https or localhost, and
 * http:// on a LAN address is neither, so on a phone testing against a laptop's
 * IP the registration is refused and the site carries on without it.
 */
function pwa_script(): string
{
    // No scope option: sw.js sits at the root of the app, so the scope the
    // browser derives from its own URL is already the one we want, and a scope
    // passed by hand is one more thing that can be wrong.
    return '<script>'
        . "if('serviceWorker' in navigator){"
        . "addEventListener('load',function(){"
        . 'navigator.serviceWorker.register(' . js(APP_BASE . '/sw.js') . ')'
        . ".catch(function(){});"
        . '});}'
        . "</script>\n";
}

/**
 * The half of the theme that has to happen before the first paint.
 *
 * Inline and synchronous in <head>, deliberately: a deferred file would run
 * after the browser has already painted the page, and a white flash on the way
 * into dark mode is the one thing a theme setting must not do.
 *
 * It always writes data-theme, resolving the device's preference when the
 * visitor has not chosen. That means the stylesheet has exactly one state to
 * describe instead of two overlapping ones — the prefers-color-scheme block in
 * app.css stays only as the fallback for somebody without JavaScript.
 *
 * It also drops the media attribute from the theme-color metas, because those
 * two ship to cover the visitor without JavaScript and are chosen by the device
 * — which is the one thing a theme that has just been overridden is not.
 */
function theme_boot(): string
{
    return '<script>(function(){'
        . 'var v=null;'
        . 'try{v=localStorage.getItem(' . js(THEME_KEY) . ')}catch(e){}'
        . 'if(v!==' . js('light') . '&&v!==' . js('dark') . '){'
        . "v=(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';}"
        . "document.documentElement.setAttribute('data-theme',v);"
        . "var m=document.querySelectorAll('meta[name=theme-color]'),"
        . "c=v==='dark'?'#0c0a09':'#fbfaf9';"
        . "for(var i=0;i<m.length;i++){m[i].removeAttribute('media');m[i].setAttribute('content',c);}"
        . '})();</script>' . "\n";
}

/**
 * The interactive half: the button, and remembering what it was set to.
 *
 * Emitted on every page by page_foot() for the same reason trip_scripts() is —
 * the toggle is part of the chrome, so every page has it and no page should have
 * to remember to ask.
 */
function theme_scripts(): string
{
    $strings = [
        'key'      => THEME_KEY,
        'dark'     => 'dark',
        'light'    => 'light',
        'to_dark'  => t('theme.to_dark'),
        'to_light' => t('theme.to_light'),
    ];

    return '<script>window.QTHEME_STRINGS = '
        . json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ";</script>\n"
        . '<script src="' . e(asset('assets/js/theme.js')) . "\" defer></script>\n";
}

/**
 * The strings and the script for "My trip".
 *
 * Emitted by page_foot() on every page rather than by the pages that need it.
 * One file, deferred, and it is the only thing that can fill the count beside
 * the nav link or make a [data-trip] button work — so the alternative is every
 * page that might ever grow a wishlist button remembering to ask for it.
 *
 * It must be emitted exactly once per page: trip.js binds a click listener on
 * document, so a second copy would toggle twice and appear to do nothing.
 */
function trip_scripts(): string
{
    // Only the strings the script actually prints. The page-rendering copy on
    // trip.php is written into the HTML by PHP, where it belongs.
    $strings = [
        'lang'            => current_lang(),
        'unit_min'        => t('unit.min'),
        'unit_hr'         => t('unit.hr'),
        'trip_add'        => t('trip.add'),
        'trip_added'      => t('trip.added'),
        'trip_add_one'    => t('trip.add_one'),
        'trip_remove_one' => t('trip.remove_one'),
        'trip_add_aria'   => t('trip.add_aria'),
        'trip_remove_aria'=> t('trip.remove_aria'),
    ];

    return '<script>window.QTRIP_STRINGS = '
        . json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ";</script>\n"
        . '<script src="' . e(asset('assets/js/trip.js')) . "\" defer></script>\n";
}

/** A standalone page for the "map data missing" state. */
function require_map_data(): void
{
    if (map_ready()) {
        return;
    }
    page_head(['title' => 'Setup needed', 'noindex' => true]);
    ?>
    <section class="wrap narrow">
      <div class="panel">
        <h1>One setup step to go</h1>
        <p class="lede">The map files have not been generated yet.</p>
        <p>Open <a href="<?= e(APP_BASE) ?>/setup/prepare.php"><code>setup/prepare.php</code></a> in your browser.
           It reads the source Qatar SVG, works out the map projection, places every locality, and writes
           <code>assets/data/*.json</code> and <code>sql/seed.sql</code>.</p>
        <p class="muted">Then import <code>sql/schema.sql</code>, <code>sql/seed-core.sql</code>,
           <code>sql/seed.sql</code>, <code>sql/seed-places.sql</code> and
           <code>sql/seed-itineraries.sql</code> in phpMyAdmin, in that order.</p>
      </div>
    </section>
    <?php
    page_foot();
    exit;
}
