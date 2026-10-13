<?php
/**
 * The places people actually tick off — 48 of them, spread across all 8 municipalities.
 *
 * IMPORTANT: `mun_hint` is only a guess kept for review. The real municipality is
 * derived at prepare time by point-in-polygon against the authoritative SVG boundaries
 * (see setup/prepare.php). If a hint disagrees with the geometry, the geometry wins and
 * the script prints a warning so it can be eyeballed.
 *
 * Coordinates are the settlement/landmark centres, to ~3 decimal places (~100m).
 * `kind` is shown as a small tag beside the name in the checklist (translated —
 * see the kind.* strings in inc/lang/), and is searchable: typing "industrial"
 * finds Ras Laffan and Mesaieed.
 *
 * Adding one here is the whole job — re-run setup/prepare.php and the dot, the
 * checklist row and the SQL seed all follow. Only the totals in inc/lang/ are
 * written by hand, and those take the count as a placeholder.
 */

return [
    // ---- Ad Dawhah (Doha) -------------------------------------------------
    ['slug' => 'doha',            'name_en' => 'Doha',              'name_ar' => 'الدوحة',            'lat' => 25.2854, 'lng' => 51.5310, 'kind' => 'city',       'mun_hint' => 'QADA'],
    ['slug' => 'souq-waqif',      'name_en' => 'Souq Waqif',        'name_ar' => 'سوق واقف',          'lat' => 25.2867, 'lng' => 51.5333, 'kind' => 'landmark',   'mun_hint' => 'QADA'],
    ['slug' => 'msheireb',        'name_en' => 'Msheireb',          'name_ar' => 'مشيرب',             'lat' => 25.2865, 'lng' => 51.5245, 'kind' => 'district',   'mun_hint' => 'QADA'],
    ['slug' => 'west-bay',        'name_en' => 'West Bay',          'name_ar' => 'الخليج الغربي',     'lat' => 25.3205, 'lng' => 51.5237, 'kind' => 'district',   'mun_hint' => 'QADA'],
    ['slug' => 'the-pearl',       'name_en' => 'The Pearl-Qatar',   'name_ar' => 'اللؤلؤة قطر',       'lat' => 25.3686, 'lng' => 51.5530, 'kind' => 'district',   'mun_hint' => 'QADA'],
    ['slug' => 'katara',          'name_en' => 'Katara',            'name_ar' => 'كتارا',             'lat' => 25.3595, 'lng' => 51.5260, 'kind' => 'landmark',   'mun_hint' => 'QADA'],
    ['slug' => 'al-sadd',         'name_en' => 'Al Sadd',           'name_ar' => 'السد',              'lat' => 25.2761, 'lng' => 51.5044, 'kind' => 'district',   'mun_hint' => 'QADA'],
    ['slug' => 'bin-mahmoud',     'name_en' => 'Bin Mahmoud',       'name_ar' => 'بن محمود',          'lat' => 25.2733, 'lng' => 51.5117, 'kind' => 'district',   'mun_hint' => 'QADA'],
    ['slug' => 'al-thumama',      'name_en' => 'Al Thumama',        'name_ar' => 'الثمامة',           'lat' => 25.2340, 'lng' => 51.5400, 'kind' => 'district',   'mun_hint' => 'QADA'],

    // ---- Al Daayen --------------------------------------------------------
    ['slug' => 'lusail',          'name_en' => 'Lusail',            'name_ar' => 'لوسيل',             'lat' => 25.4300, 'lng' => 51.4900, 'kind' => 'city',       'mun_hint' => 'QAZA'],
    ['slug' => 'al-daayen',       'name_en' => 'Al Daayen',         'name_ar' => 'الضعاين',           'lat' => 25.5000, 'lng' => 51.4500, 'kind' => 'town',       'mun_hint' => 'QAZA'],

    // ---- Al Khor ----------------------------------------------------------
    ['slug' => 'al-khor',         'name_en' => 'Al Khor',           'name_ar' => 'الخور',             'lat' => 25.6804, 'lng' => 51.4969, 'kind' => 'city',       'mun_hint' => 'QAKH'],
    ['slug' => 'al-thakhira',     'name_en' => 'Al Thakhira',       'name_ar' => 'الذخيرة',           'lat' => 25.7361, 'lng' => 51.5483, 'kind' => 'town',       'mun_hint' => 'QAKH'],
    ['slug' => 'simaisma',        'name_en' => 'Simaisma',          'name_ar' => 'سميسمة',            'lat' => 25.5833, 'lng' => 51.5333, 'kind' => 'town',       'mun_hint' => 'QAKH'],
    ['slug' => 'al-ghuwariyah',   'name_en' => 'Al Ghuwariyah',     'name_ar' => 'الغويرية',          'lat' => 25.7000, 'lng' => 51.5000, 'kind' => 'town',       'mun_hint' => 'QAKH'],
    ['slug' => 'ras-laffan',      'name_en' => 'Ras Laffan',        'name_ar' => 'رأس لفان',          'lat' => 25.9000, 'lng' => 51.5500, 'kind' => 'industrial', 'mun_hint' => 'QAKH'],

    // ---- Al Shamal --------------------------------------------------------
    ['slug' => 'madinat-ash-shamal', 'name_en' => 'Madinat ash Shamal', 'name_ar' => 'مدينة الشمال',  'lat' => 26.1200, 'lng' => 51.2200, 'kind' => 'city',       'mun_hint' => 'QAMS'],
    ['slug' => 'al-ruwais',       'name_en' => 'Al Ruwais',         'name_ar' => 'الرويس',            'lat' => 26.1333, 'lng' => 51.2167, 'kind' => 'town',       'mun_hint' => 'QAMS'],
    ['slug' => 'abu-dhalouf',     'name_en' => 'Abu Dhalouf',       'name_ar' => 'أبو ظلوف',          'lat' => 26.1000, 'lng' => 51.2333, 'kind' => 'town',       'mun_hint' => 'QAMS'],
    ['slug' => 'fuwayrit',        'name_en' => 'Fuwayrit',          'name_ar' => 'فويرط',             'lat' => 26.0333, 'lng' => 51.3667, 'kind' => 'town',       'mun_hint' => 'QAMS'],
    ['slug' => 'al-zubarah',      'name_en' => 'Al Zubarah',        'name_ar' => 'الزبارة',           'lat' => 25.9775, 'lng' => 51.0275, 'kind' => 'natural',    'mun_hint' => 'QAMS'],

    // ---- Umm Salal --------------------------------------------------------
    ['slug' => 'umm-salal-ali',      'name_en' => 'Umm Salal Ali',      'name_ar' => 'أم صلال علي',   'lat' => 25.4167, 'lng' => 51.4000, 'kind' => 'town', 'mun_hint' => 'QAUS'],
    ['slug' => 'umm-salal-mohammed', 'name_en' => 'Umm Salal Mohammed', 'name_ar' => 'أم صلال محمد',  'lat' => 25.4167, 'lng' => 51.4333, 'kind' => 'town', 'mun_hint' => 'QAUS'],

    // ---- Al Shahaniya -----------------------------------------------------
    ['slug' => 'al-shahaniya',    'name_en' => 'Al Shahaniya',      'name_ar' => 'الشحانية',          'lat' => 25.3700, 'lng' => 51.2200, 'kind' => 'city',       'mun_hint' => 'QASH'],
    ['slug' => 'al-jemailiya',    'name_en' => 'Al Jemailiya',      'name_ar' => 'الجميلية',          'lat' => 25.5667, 'lng' => 51.1000, 'kind' => 'town',       'mun_hint' => 'QASH'],
    ['slug' => 'al-utouriya',     'name_en' => 'Al Utouriya',       'name_ar' => 'العطورية',          'lat' => 25.4667, 'lng' => 51.0833, 'kind' => 'town',       'mun_hint' => 'QASH'],
    ['slug' => 'al-nasraniya',    'name_en' => 'Al Nasraniya',      'name_ar' => 'النصرانية',         'lat' => 25.3833, 'lng' => 51.1167, 'kind' => 'town',       'mun_hint' => 'QASH'],
    ['slug' => 'dukhan',          'name_en' => 'Dukhan',            'name_ar' => 'دخان',              'lat' => 25.2417, 'lng' => 50.7853, 'kind' => 'industrial', 'mun_hint' => 'QASH'],
    ['slug' => 'umm-bab',         'name_en' => 'Umm Bab',           'name_ar' => 'أم باب',            'lat' => 25.2167, 'lng' => 50.8000, 'kind' => 'town',       'mun_hint' => 'QASH'],
    ['slug' => 'al-karaana',      'name_en' => 'Al Karaana',        'name_ar' => 'الكرعانة',          'lat' => 25.1333, 'lng' => 51.1667, 'kind' => 'town',       'mun_hint' => 'QASH'],
    ['slug' => 'rawdat-rashed',   'name_en' => 'Rawdat Rashed',     'name_ar' => 'روضة راشد',         'lat' => 25.2333, 'lng' => 51.2167, 'kind' => 'town',       'mun_hint' => 'QASH'],
    ['slug' => 'zekreet',         'name_en' => 'Zekreet',           'name_ar' => 'زكريت',             'lat' => 25.5167, 'lng' => 50.8333, 'kind' => 'natural',    'mun_hint' => 'QASH'],

    // ---- Ar Rayyan --------------------------------------------------------
    ['slug' => 'al-rayyan',       'name_en' => 'Al Rayyan',         'name_ar' => 'الريان',            'lat' => 25.2900, 'lng' => 51.4200, 'kind' => 'city',       'mun_hint' => 'QARA'],
    ['slug' => 'al-wajba',        'name_en' => 'Al Wajba',          'name_ar' => 'الوجبة',            'lat' => 25.2667, 'lng' => 51.3667, 'kind' => 'town',       'mun_hint' => 'QARA'],
    ['slug' => 'education-city',  'name_en' => 'Education City',    'name_ar' => 'المدينة التعليمية', 'lat' => 25.3140, 'lng' => 51.4400, 'kind' => 'landmark',   'mun_hint' => 'QARA'],
    ['slug' => 'aspire-zone',     'name_en' => 'Aspire Zone',       'name_ar' => 'أسباير',            'lat' => 25.2630, 'lng' => 51.4430, 'kind' => 'landmark',   'mun_hint' => 'QARA'],
    ['slug' => 'abu-nakhla',      'name_en' => 'Abu Nakhla',        'name_ar' => 'أبو نخلة',          'lat' => 25.2167, 'lng' => 51.3833, 'kind' => 'town',       'mun_hint' => 'QARA'],
    ['slug' => 'mebaireek',       'name_en' => 'Mebaireek',         'name_ar' => 'مبيريك',            'lat' => 25.2333, 'lng' => 51.4167, 'kind' => 'town',       'mun_hint' => 'QARA'],
    ['slug' => 'industrial-area', 'name_en' => 'Industrial Area',   'name_ar' => 'المنطقة الصناعية',  'lat' => 25.2100, 'lng' => 51.4100, 'kind' => 'industrial', 'mun_hint' => 'QARA'],
    ['slug' => 'sawda-natheel',   'name_en' => 'Sawda Natheel',     'name_ar' => 'سودا نثيل',         'lat' => 24.6167, 'lng' => 51.1167, 'kind' => 'natural',    'mun_hint' => 'QARA'],
    ['slug' => 'abu-samra',       'name_en' => 'Abu Samra',         'name_ar' => 'أبو سمرة',          'lat' => 24.7500, 'lng' => 50.8167, 'kind' => 'natural',    'mun_hint' => 'QARA'],

    // ---- Al Wakrah --------------------------------------------------------
    ['slug' => 'al-wakrah',       'name_en' => 'Al Wakrah',         'name_ar' => 'الوكرة',            'lat' => 25.1652, 'lng' => 51.6034, 'kind' => 'city',       'mun_hint' => 'QAWA'],
    ['slug' => 'mesaieed',        'name_en' => 'Mesaieed',          'name_ar' => 'مسيعيد',            'lat' => 24.9907, 'lng' => 51.5497, 'kind' => 'industrial', 'mun_hint' => 'QAWA'],
    ['slug' => 'sealine',         'name_en' => 'Sealine',           'name_ar' => 'سيلين',             'lat' => 24.8833, 'lng' => 51.5333, 'kind' => 'natural',    'mun_hint' => 'QAWA'],
    ['slug' => 'al-kharrara',     'name_en' => 'Al Kharrara',       'name_ar' => 'الخرارة',           'lat' => 24.9167, 'lng' => 51.3167, 'kind' => 'town',       'mun_hint' => 'QAWA'],
    ['slug' => 'shagra',          'name_en' => 'Shagra',            'name_ar' => 'شقرا',              'lat' => 24.7833, 'lng' => 51.4167, 'kind' => 'town',       'mun_hint' => 'QAWA'],
    ['slug' => 'mazra-at-turayana','name_en' => 'Mazra at Turayana', 'name_ar' => 'مزرعة طريانة',     'lat' => 24.7333, 'lng' => 51.3167, 'kind' => 'town',       'mun_hint' => 'QAWA'],
    ['slug' => 'al-wukair',       'name_en' => 'Al Wukair',         'name_ar' => 'الوكير',            'lat' => 25.1500, 'lng' => 51.5500, 'kind' => 'town',       'mun_hint' => 'QAWA'],
];
