<?php
/**
 * The visitor guide — visa, transport, customs, the practical pages.
 *
 * Every page carries a source URL and a check date. That is not decoration: this
 * is the content that goes stale fastest and hurts most when it does, so the
 * screen shows how long ago each page was checked and the editor refuses to
 * pretend a page with no source is finished.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

simple_crud([
    'page'      => 'guide.php',
    'nav'       => 'guide',
    'table'     => 'guide_pages',
    'module'    => 'guide',
    'entity'    => 'guide_page',
    'singular'  => 'Guide page',
    'title'     => 'Visitor guide',
    'sub'       => 'The practical pages: visas, transport, customs, money, and the mistakes people make.',
    'order'     => 'section, sort_order, title_en',
    'search'    => ['title_en', 'title_ar', 'slug'],
    'soft'      => true,
    'deletable' => true,
    'has_status' => true,

    'columns' => [
        ['k' => 'title_en', 't' => 'Page', 'fmt' => static function (array $r): string {
            $out = '<a class="ad-rowlink" href="' . e(admin_url('guide.php', ['id' => (int) $r['id']])) . '">'
                 . e((string) $r['title_en']) . '</a>';
            if ((string) $r['source_url'] === '') {
                $out .= '<span class="ad-sub">' . ad_chip('no source', 'bad') . '</span>';
            }
            return $out;
        }],
        ['k' => 'section', 't' => 'Section', 'fmt' => static fn(array $r): string =>
            ad_chip((string) $r['section'])],
        ['k' => 'verified_at', 't' => 'Checked', 'fmt' => static function (array $r): string {
            if ($r['verified_at'] === null) {
                return '<span class="ad-chip warn">never</span>';
            }
            $days = (int) floor((time() - strtotime((string) $r['verified_at'])) / 86400);
            return $days > 180
                ? ad_chip((int) floor($days / 30) . ' months ago', 'warn')
                : e(ad_when((string) $r['verified_at']));
        }],
        ['k' => 'status', 't' => 'Status', 'fmt' => static fn(array $r): string =>
            ad_status_chip((string) $r['status'])],
    ],

    'fields' => [
        ['k' => 'title_en', 't' => 'Title (English)', 'required' => true, 'maxlen' => 160],
        ['k' => 'slug', 't' => 'Slug', 'type' => 'slug', 'from' => 'title_en', 'maxlen' => 64],
        ['k' => 'title_ar', 't' => 'Title (Arabic)', 'required' => true, 'maxlen' => 160],
        ['k' => 'section', 't' => 'Section', 'type' => 'enum',
         'values' => ['visa', 'transport', 'customs', 'practical', 'mistakes'], 'default' => 'practical',
         'help' => 'Groups the page in the guide sidebar. "mistakes" is the common-visitor-mistakes list.'],
        ['k' => 'body_en', 't' => 'Body (English)', 'type' => 'textarea', 'rows' => 12,
         'help' => 'Plain sentences. If a fact can change — a fee, an opening time, a rule — say when it was last true and link the source below.'],
        ['k' => 'body_ar', 't' => 'Body (Arabic)', 'type' => 'textarea', 'rows' => 12],
        ['k' => 'source_url', 't' => 'Source', 'type' => 'url', 'maxlen' => 255,
         'help' => 'The government or operator page this came from. A guide page without one is a rumour.'],
        ['k' => 'source_note', 't' => 'Source note', 'maxlen' => 160,
         'ph' => 'e.g. Ministry of Interior, visited 12 Oct'],
        ['k' => 'verified_at', 't' => 'Details checked', 'type' => 'date'],
        ['k' => 'status', 't' => 'Status', 'type' => 'enum',
         'values' => ['draft', 'published', 'archived'], 'default' => 'draft'],
        ['k' => 'sort_order', 't' => 'Sort order', 'type' => 'number', 'min' => -999, 'max' => 999],
    ],
]);
