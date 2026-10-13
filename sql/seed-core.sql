-- ============================================================================
--  Qatar Travel Map — static seed content
--
--  Things that are authored once and rarely change: the category list, the
--  gamification rules, and the share-card themes.
--
--  This file is hand-written. The map data (municipalities, localities, places)
--  is generated into seed.sql by setup/prepare.php.
--
--  Import order:  schema.sql  ->  seed.sql  ->  seed-core.sql
-- ============================================================================

SET NAMES utf8mb4;
USE `qatar_map`;

DELETE FROM share_themes;
DELETE FROM personalities;
DELETE FROM badges;
DELETE FROM levels;
DELETE FROM tags;
DELETE FROM categories;


-- ---------------------------------------------------------------------------
--  Categories
-- ---------------------------------------------------------------------------
INSERT INTO categories (slug, name_en, name_ar, icon, color, sort_order) VALUES
  ('heritage',  'Heritage',        'التراث',          '🏛️', '#b45309', 10),
  ('museum',    'Museum',          'متحف',            '🖼️', '#7c3aed', 20),
  ('culture',   'Culture & Arts',  'ثقافة وفنون',     '🎭', '#db2777', 30),
  ('nature',    'Nature',          'طبيعة',           '🌿', '#16a34a', 40),
  ('beach',     'Beach',           'شاطئ',            '🏖️', '#0891b2', 50),
  ('desert',    'Desert',          'صحراء',           '🐪', '#d97706', 60),
  ('park',      'Park & Garden',   'حديقة',           '🌳', '#65a30d', 70),
  ('souq',      'Souq & Market',   'سوق',             '🧺', '#a16207', 80),
  ('mall',      'Shopping',        'تسوق',            '🛍️', '#4f46e5', 90),
  ('food',      'Food',            'طعام',            '🍽️', '#dc2626', 100),
  ('family',    'Family',          'عائلي',           '👨‍👩‍👧', '#0d9488', 110),
  ('sport',     'Sport',           'رياضة',           '⚽', '#2563eb', 120),
  ('landmark',  'Landmark',        'معلم',            '📍', '#475569', 130);

-- ---------------------------------------------------------------------------
--  Tags — free-form facets people actually search by
-- ---------------------------------------------------------------------------
INSERT INTO tags (slug, name_en, name_ar) VALUES
  ('sunset',      'Sunset',        'الغروب'),
  ('sunrise',     'Sunrise',       'الشروق'),
  ('photography', 'Photography',   'تصوير'),
  ('free',        'Free entry',    'دخول مجاني'),
  ('indoor',      'Indoor',        'داخلي'),
  ('kid-friendly','Kid friendly',  'مناسب للأطفال'),
  ('quiet',       'Quiet',         'هادئ'),
  ('crowded',     'Busy',          'مزدحم'),
  ('walkable',    'Walkable',      'قابل للمشي'),
  ('viewpoint',   'Viewpoint',     'إطلالة'),
  ('unesco',      'UNESCO',        'يونسكو'),
  ('camping',     'Camping',       'تخييم'),
  ('swimming',    'Swimming',      'سباحة'),
  ('camel',       'Camels',        'جمال'),
  ('dhow',        'Traditional boats', 'قوارب تقليدية');

-- ---------------------------------------------------------------------------
--  Levels — thresholds match the 48 markable localities
-- ---------------------------------------------------------------------------
INSERT INTO levels (slug, name_en, name_ar, min_places, color, sort_order) VALUES
  ('newcomer',     'Newcomer',      'وافد جديد',     0,  '#94a3b8', 10),
  ('settling-in',  'Settling In',   'يتأقلم',        6,  '#38bdf8', 20),
  ('explorer',     'Explorer',      'مستكشف',        14, '#22c55e', 30),
  ('trailblazer',  'Trailblazer',   'رائد',          24, '#eab308', 40),
  ('qatar-master', 'Qatar Master',  'خبير قطر',      36, '#f97316', 50),
  ('completionist','Completionist', 'المكتمل',       48, '#8b1538', 60);

-- ---------------------------------------------------------------------------
--  Badges
--
--  rule_json is evaluated client-side against the selection the user has made,
--  so a new badge is a row insert rather than a code change. Supported rules:
--    {"type":"count","n":N}                       — N places marked in total
--    {"type":"municipalities_all"}                — at least one in every municipality
--    {"type":"municipalities","n":N}
--    {"type":"kind","kind":"natural","n":N}       — by localities.kind
--    {"type":"category","slug":"beach","n":N}     — by places.category
--    {"type":"municipality","code":"QAMS","all":1}— everything in one municipality
-- ---------------------------------------------------------------------------
INSERT INTO badges (slug, name_en, name_ar, description_en, description_ar, icon, rule_json, sort_order) VALUES
  ('first-step', 'First Step', 'الخطوة الأولى',
   'Mark your first place.', 'علّم أول مكان لك.',
   '👣', '{"type":"count","n":1}', 10),

  ('all-eight', 'All 8 Municipalities', 'البليديات الثماني',
   'Have something marked in every one of Qatar''s eight municipalities.',
   'علّم مكاناً في كل بلدية من بلديات قطر الثماني.',
   '🗺️', '{"type":"municipalities_all"}', 20),

  ('desert-walker', 'Desert Walker', 'سالك الصحراء',
   'Reach three desert and wilderness spots.', 'اوصل إلى ثلاثة مواقع صحراوية.',
   '🐪', '{"type":"kind","kind":"natural","n":3}', 30),

  ('beach-hunter', 'Beach Hunter', 'صياد الشواطئ',
   'Visit three beaches.', 'زر ثلاثة شواطئ.',
   '🏖️', '{"type":"category","slug":"beach","n":3}', 40),

  ('museum-lover', 'Museum Lover', 'محب المتاحف',
   'Visit four museums.', 'زر أربعة متاحف.',
   '🖼️', '{"type":"category","slug":"museum","n":4}', 50),

  ('souq-shopper', 'Souq Regular', 'من رواد الأسواق',
   'Browse three souqs or malls.', 'تجول في ثلاثة أسواق أو مراكز تجارية.',
   '🧺', '{"type":"category","slug":"souq","n":3}', 60),

  ('north-runner', 'North Runner', 'شمالي',
   'Cover every place in Al Shamal, the northern tip.',
   'غطِّ كل مكان في بلدية الشمال.',
   '🧭', '{"type":"municipality","code":"QAMS","all":1}', 70),

  ('half-way', 'Half Way', 'منتصف الطريق',
   'Mark 24 of the 48 places.', 'علّم ٢٤ من ٤٨ مكاناً.',
   '⚡', '{"type":"count","n":24}', 80),

  ('completionist', 'Completionist', 'المكتمل',
   'Mark every single place. All 48.', 'علّم كل مكان. جميع الـ ٤٨.',
   '🏆', '{"type":"count","n":48}', 90);

-- ---------------------------------------------------------------------------
--  Travel personalities
--
--  weights_json scores each personality against the kinds and categories the
--  user marked; the highest total wins. Negative weights push a personality
--  away — marking a beach should make you less likely to be a "Desert Soul".
--
--  Weight keys (see assets/js/gamify.js):
--    "kind:natural"  — per marked locality of that kind
--    "cat:beach"     — per marked place in that category (needs place data)
--    "mun:QAMS"      — per marked locality in that municipality
--    "count:40"      — flat bonus once that many places are marked
--    "any"           — flat bonus, always
--  A personality scoring 0 or less is not shown at all, which is why the
--  completionist below is gated on 40 rather than given an unconditional bump.
-- ---------------------------------------------------------------------------
INSERT INTO personalities (slug, name_en, name_ar, blurb_en, blurb_ar, weights_json, priority) VALUES
  ('desert-soul', 'Desert Soul', 'روح الصحراء',
   'You go where the road runs out. Dunes, forts and long empty horizons.',
   'تذهب حيث ينتهي الطريق. كثبان وقلاع وآفاق فارغة.',
   '{"kind:natural":3,"kind:industrial":-1,"cat:beach":-2,"cat:desert":4,"cat:heritage":2}', 10),

  ('coastline-chaser', 'Coastline Chaser', 'تابع الساحل',
   'You measure a weekend by how much of it you spent near water.',
   'تقيس عطلتك بمقدار الوقت الذي قضيته قرب الماء.',
   '{"cat:beach":4,"kind:natural":2,"cat:park":-1,"cat:mall":-2}', 20),

  ('city-explorer', 'City Explorer', 'مستكشف المدينة',
   'Museums, souqs, skylines. You would rather walk a city than drive past it.',
   'متاحف وأسواق وأفق المدن. تفضل المشي في المدينة على المرور بها.',
   '{"kind:district":3,"kind:city":2,"cat:museum":3,"cat:souq":2,"kind:natural":-2}', 30),

  ('heritage-hunter', 'Heritage Hunter', 'باحث التراث',
   'Old walls, old wells, old stories. The country before the glass towers.',
   'جدران وآبار وحكايات قديمة. البلد قبل الأبراج الزجاجية.',
   '{"cat:heritage":4,"cat:museum":2,"cat:mall":-3,"cat:sport":-1}', 40),

  ('family-planner', 'Family Planner', 'منظّم الرحلات العائلية',
   'Every trip has to work for everyone in the car.',
   'كل رحلة يجب أن تناسب كل من في السيارة.',
   '{"cat:family":4,"cat:park":3,"cat:sport":2,"cat:desert":-1}', 50),

  ('completionist', 'The Completionist', 'المكتمل',
   'You are not here for a vibe. You are here to finish the list.',
   'أنت لا تبحث عن مزاج معين. أنت هنا لإكمال القائمة.',
   '{"count:40":1}', 99);

-- ---------------------------------------------------------------------------
--  Share card themes
--
--  `land` is an unvisited municipality, `visited` is one the user has ticked.
--  The live map and the exported canvas read the same values, so the downloaded
--  card always matches what was on screen.
-- ---------------------------------------------------------------------------
INSERT INTO share_themes (slug, name_en, name_ar, bg, land, visited, accent, ink, ink_soft, is_dark, sort_order) VALUES
  ('maroon', 'Maroon',  'عنابي',
   '#ffffff', '#e7e2df', '#8b1538', '#8b1538', '#1c1917', '#78716c', 0, 10),

  ('desert', 'Desert',  'صحراوي',
   '#fdf8ef', '#efe2c9', '#c2703a', '#a65a28', '#3f2d16', '#8a7355', 0, 20),

  ('pearl',  'Pearl',   'لؤلؤي',
   '#f6fbfd', '#dceaf0', '#2b7a9b', '#1f6c8c', '#0f2b36', '#5b8291', 0, 30),

  ('night',  'Night',   'ليلي',
   '#0b1220', '#1e293b', '#60a5fa', '#93c5fd', '#f1f5f9', '#94a3b8', 1, 40),

  ('flag',   'Flag',    'العلم',
   '#ffffff', '#f0ece9', '#8b1538', '#8b1538', '#1c1917', '#78716c', 0, 50),

  ('slate',  'Slate',   'رمادي',
   '#f8fafc', '#e2e8f0', '#334155', '#0f172a', '#0f172a', '#64748b', 0, 60);

-- ---------------------------------------------------------------------------
--  Settings
--
--  The keys must match setting_defaults() in inc/settings.php exactly. A row
--  whose key nothing looks up is a setting that silently does nothing — which
--  is why these are dotted and prefixed by area, not bare words.
--
--  The rows for values that change how the *code* behaves (the snapshot cap,
--  the save rate limit, whether the site is in maintenance) stay here only
--  where setting() reads them. Limits live in inc/config.php, where they are
--  version-controlled; anything an editor should be able to change without a
--  deploy lives here.
-- ---------------------------------------------------------------------------
INSERT INTO settings (k, v) VALUES
  ('site.name',         'Qatar Unseen'),
  ('site.tagline',      'How much of Qatar have you actually seen?'),
  ('site.default_lang', 'en'),
  ('footer.disclaimer', 'Details change. Check official sources before you travel.'),
  ('contact.email',     ''),
  ('social.instagram',  ''),
  ('social.x',          ''),
  ('social.tiktok',     ''),
  ('seo.default_title', ''),
  ('seo.default_desc',  ''),
  ('map.default_zoom',  'fit'),
  ('maintenance',       '0'),
  ('maintenance_note',  ''),
  ('analytics.enabled', '1')
ON DUPLICATE KEY UPDATE v = VALUES(v);
