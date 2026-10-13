<?php
/**
 * Personality cards — "Desert Soul", "City Explorer".
 *
 * Each personality scores the visitor's marks against a set of weights and the
 * highest total wins. Negative weights are allowed and useful: "City Explorer"
 * wanting -1 for every desert locality is a clearer way to say "this is not
 * them" than leaving it out.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

simple_crud([
    'page'      => 'personalities.php',
    'nav'       => 'persona',
    'table'     => 'personalities',
    'module'    => 'personality',
    'entity'    => 'personality',
    'singular'  => 'Personality',
    'title'     => 'Personalities',
    'sub'       => 'The card a visitor is given after they finish their map.',
    'order'     => 'priority DESC, name_en',
    'search'    => ['name_en', 'name_ar', 'blurb_en', 'slug'],
    'deletable' => true,

    'columns' => [
        ['k' => 'name_en', 't' => 'Personality'],
        ['k' => 'name_ar', 't' => 'Arabic'],
        ['k' => 'blurb_en', 't' => 'Blurb'],
        ['k' => 'weights_json', 't' => 'Weights',
         'fmt' => static function (array $r): string {
             $j = json_decode((string) $r['weights_json'], true);
             if (!is_array($j) || $j === []) {
                 return '<span class="ad-chip bad">empty</span>';
             }
             $bits = [];
             foreach ($j as $k => $v) {
                 $n = (int) $v;
                 $bits[] = e((string) $k) . (($n >= 0 ? '+' : '') . $n);
             }
             return '<code class="ad-muted">' . implode(' ', $bits) . '</code>';
         }],
        ['k' => 'priority', 't' => 'Priority', 'num' => true,
         'fmt' => static fn(array $r): string => (string) (int) $r['priority']],
    ],

    'fields' => [
        ['k' => 'name_en', 't' => 'Name (English)', 'required' => true, 'maxlen' => 64],
        ['k' => 'slug', 't' => 'Slug', 'type' => 'slug', 'from' => 'name_en', 'maxlen' => 32,
         'help' => 'Saved maps store this slug alongside the card.'],
        ['k' => 'name_ar', 't' => 'Name (Arabic)', 'required' => true, 'maxlen' => 64],
        ['k' => 'blurb_en', 't' => 'Blurb (English)', 'type' => 'textarea', 'rows' => 2, 'maxlen' => 255,
         'help' => 'One or two sentences, written to the visitor — this is printed on their card.'],
        ['k' => 'blurb_ar', 't' => 'Blurb (Arabic)', 'type' => 'textarea', 'rows' => 2, 'maxlen' => 255],
        ['k' => 'weights_json', 't' => 'Weights', 'type' => 'json', 'required' => true, 'rows' => 4,
         'help' => 'Keyed by locality <code>kind</code> (city, town, district, landmark, industrial, '
                 . 'natural) or by category slug. Negative numbers count against.<br>'
                 . '<code>{"natural":3,"landmark":1,"city":-1}</code>'],
        ['k' => 'priority', 't' => 'Priority', 'type' => 'number', 'min' => -999, 'max' => 999,
         'help' => 'Breaks ties when two personalities score the same. Higher wins.'],
    ],
]);
