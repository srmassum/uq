<?php
/**
 * Badges.
 *
 * The rule is JSON rather than a row of columns, so a badge can be invented
 * without a code change. The four shapes the front end understands are listed
 * in the field help, because a free-text JSON box with no examples is a box
 * nobody fills in correctly.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

simple_crud([
    'page'      => 'badges.php',
    'nav'       => 'badges',
    'table'     => 'badges',
    'module'    => 'badge',
    'entity'    => 'badge',
    'singular'  => 'Badge',
    'title'     => 'Badges',
    'sub'       => 'Earned automatically as a visitor ticks places off. Switch one off to hide it without losing the record.',
    'order'     => 'sort_order, name_en',
    'search'    => ['name_en', 'name_ar', 'slug', 'description_en'],
    'deletable' => true,

    'columns' => [
        ['k' => 'name_en', 't' => 'Badge'],
        ['k' => 'name_ar', 't' => 'Arabic'],
        ['k' => 'rule_json', 't' => 'Rule',
         'fmt' => static function (array $r): string {
             $j = json_decode((string) $r['rule_json'], true);
             if (!is_array($j)) {
                 return '<span class="ad-chip bad">unreadable</span>';
             }
             $bits = [];
             foreach ($j as $k => $v) {
                 $bits[] = e((string) $k) . '=' . e(is_scalar($v) ? (string) $v : gettype($v));
             }
             return '<code class="ad-muted">' . implode(' ', $bits) . '</code>';
         }],
        ['k' => 'is_active', 't' => 'On', 'num' => true,
         'fmt' => static fn(array $r): string => (int) $r['is_active']
             ? ad_chip('active', 'ok')
             : ad_chip('off', 'mute')],
        ['k' => 'sort_order', 't' => 'Order', 'num' => true,
         'fmt' => static fn(array $r): string => (string) (int) $r['sort_order']],
    ],

    'fields' => [
        ['k' => 'name_en', 't' => 'Name (English)', 'required' => true, 'maxlen' => 64],
        ['k' => 'slug', 't' => 'Slug', 'type' => 'slug', 'from' => 'name_en', 'maxlen' => 48],
        ['k' => 'name_ar', 't' => 'Name (Arabic)', 'required' => true, 'maxlen' => 64],
        ['k' => 'description_en', 't' => 'How it is earned (English)', 'type' => 'textarea', 'rows' => 2, 'maxlen' => 255],
        ['k' => 'description_ar', 't' => 'How it is earned (Arabic)', 'type' => 'textarea', 'rows' => 2, 'maxlen' => 255],
        ['k' => 'icon', 't' => 'Icon', 'maxlen' => 32],
        ['k' => 'rule_json', 't' => 'Rule', 'type' => 'json', 'required' => true, 'rows' => 4,
         'help' => 'One of:<br>'
                 . '<code>{"type":"municipalities_all"}</code> — every municipality ticked<br>'
                 . '<code>{"type":"count","n":10}</code> — any ten places<br>'
                 . '<code>{"type":"kind","kind":"natural","n":3}</code> — three of one locality kind<br>'
                 . '<code>{"type":"category","slug":"beach","n":5}</code> — five of one category'],
        ['k' => 'is_active', 't' => 'Active', 'type' => 'checkbox', 'on' => 'Show this badge to visitors'],
        ['k' => 'sort_order', 't' => 'Sort order', 'type' => 'number', 'min' => 0, 'max' => 999],
    ],
]);
