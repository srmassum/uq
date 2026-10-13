<?php
/**
 * sitemap.xml, generated rather than written out.
 *
 * Named sitemap.php on purpose: the project's .htaccess has no rewrite rules at
 * all, so a file called sitemap.xml would be a static document that starts
 * drifting the moment somebody adds a place in the panel and forgets to
 * regenerate it. A generated sitemap cannot drift, and the cost is one line in
 * robots.txt pointing at this name.
 *
 * Every URL is listed in both languages, because on this site the language is
 * chosen by a cookie and a crawler arrives without one — so the ?lang= forms are
 * the only addresses at which the Arabic copy exists at all. Each language's
 * entry carries the alternates for the others, which is how the pair is meant to
 * be declared: the annotation belongs on every URL in the set, not just one.
 *
 * Deliberately absent: the shared-map pages (s.php, noindex by choice), the trip
 * page (its content lives in one browser, so a crawler would see an empty page),
 * and anything at all in /admin, /api, /setup or /inc.
 */

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/places.php';
require __DIR__ . '/inc/itineraries.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

/**
 * One <url> entry.
 *
 * @param array<string,string> $alternates lang code => absolute URL
 */
function sm_url(string $loc, string $lastmod, array $alternates, string $priority): string
{
    $out = "  <url>\n";
    $out .= '    <loc>' . htmlspecialchars($loc, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</loc>\n";

    // The annotation is symmetric by definition — this URL is the Arabic one for
    // the English URL and vice versa — so every entry carries all of them,
    // including a link back to itself. That is what the specification asks for.
    foreach ($alternates as $code => $href) {
        $out .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($code, ENT_QUOTES | ENT_XML1, 'UTF-8')
              . '" href="' . htmlspecialchars($href, ENT_QUOTES | ENT_XML1, 'UTF-8') . "\"/>\n";
    }

    if ($lastmod !== '') {
        $out .= '    <lastmod>' . htmlspecialchars($lastmod, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</lastmod>\n";
    }
    $out .= '    <priority>' . $priority . "</priority>\n";
    $out .= "  </url>\n";

    return $out;
}

/** The language variants of one relative URL, as an ordered map. */
function sm_variants(string $rel): array
{
    $out = [];
    foreach (SUPPORTED_LANGS as $code) {
        $out[$code] = lang_url($code, $rel);
    }
    return $out;
}

// ---------------------------------------------------------------------------
// The pages
// ---------------------------------------------------------------------------

// 'relative URL' => [priority, lastmod]. The fixed pages have no lastmod: the
// database knows nothing about when they changed, and a lastmod invented from
// today's date is a claim the site cannot support. Search engines treat a
// missing lastmod as "unknown", which is the truth.
//
// Filtered lists are deliberately not listed. The plans page carries a length
// and an audience filter, and every combination of them is a real page — which
// is exactly why listing them all would be a hundred near-duplicates pointing
// at twelve plans. The unfiltered page is the one worth submitting.
$pages = [
    'index.php'       => '1.0',
    'places.php'      => '0.9',
    'itineraries.php' => '0.8',
    'guide.php'       => '0.7',
];

$body = '';

foreach ($pages as $rel => $priority) {
    $variants = sm_variants($rel);
    foreach ($variants as $loc) {
        $body .= sm_url($loc, '', $variants, $priority);
    }
}

// Every published place, in both languages. This is the part that has to be
// generated: the list changes whenever an editor publishes one.
//
// The query is written out here rather than going through places_list(), because
// a sitemap wants two columns and asking for the other twenty-eight would make
// it the most expensive page on the site.
if (places_ready()) {
    try {
        $rows = qAll("
            SELECT slug, updated_at
              FROM places
             WHERE status = 'published' AND deleted_at IS NULL
             ORDER BY municipality_id, sort_order, name_en");
    } catch (Throwable $e) {
        error_log('sitemap: ' . $e->getMessage());
        $rows = [];
    }

    foreach ($rows as $row) {
        $rel = 'place.php?slug=' . rawurlencode((string) $row['slug']);

        // Date only. A timestamp invites a crawler to come back looking for a
        // change nobody made, and the panel does not promise that an edit moved
        // anything a visitor would see.
        $stamp    = (string) ($row['updated_at'] ?? '');
        $lastmod  = $stamp !== '' ? substr($stamp, 0, 10) : '';
        $variants = sm_variants($rel);

        foreach ($variants as $loc) {
            $body .= sm_url($loc, $lastmod, $variants, '0.8');
        }
    }
}

// Every published plan, in both languages, on the same terms as the places.
//
// The same two-column query rather than itineraries_list(), for the same reason:
// the list page needs the stop counts and the intro text, and a sitemap needs
// neither.
if (itineraries_ready()) {
    try {
        $plans = qAll("
            SELECT slug, updated_at
              FROM itineraries
             WHERE status = 'published' AND deleted_at IS NULL
             ORDER BY sort_order, title_en");
    } catch (Throwable $e) {
        error_log('sitemap (plans): ' . $e->getMessage());
        $plans = [];
    }

    foreach ($plans as $plan) {
        $rel = 'itinerary.php?slug=' . rawurlencode((string) $plan['slug']);

        $stamp    = (string) ($plan['updated_at'] ?? '');
        $lastmod  = $stamp !== '' ? substr($stamp, 0, 10) : '';
        $variants = sm_variants($rel);

        foreach ($variants as $loc) {
            $body .= sm_url($loc, $lastmod, $variants, '0.8');
        }
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
<?= $body ?>
</urlset>
