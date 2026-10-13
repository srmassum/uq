<?php
/**
 * Arabic UI strings.
 *
 * Written as natural Arabic rather than a literal translation of the English —
 * "Start marking" becomes «ابدأ التعليم» because that is what the button does,
 * not a word-for-word rendering. Missing keys fall back to English.
 */

return [
    // -- chrome ------------------------------------------------------------
    'site.name'            => 'قطر الخفية',
    'site.tagline'         => 'كم من قطر رأيته فعلاً؟',

    'nav.map'              => 'الخريطة',
    'nav.places'           => 'الأماكن',
    'nav.guide'            => 'دليل الزائر',
    'nav.about'            => 'حول',

    // -- appearance --------------------------------------------------------
    // حالتان، ونظام التشغيل هو نقطة البداية: من لا يلمس الزر يحصل على ما يطلبه
    // جهازه، ومن يلمسه يحصل على ما اختاره. ولا حالة ثالثة «اتبع النظام» ليفهمها.
    //
    // كل عبارة تصف ما سيفعله الضغط، لا ما هو عليه المظهر الآن، وهو ما تعرضه
    // الأيقونة أيضاً.
    'theme.to_dark'        => 'التبديل إلى المظهر الداكن',
    'theme.to_light'       => 'التبديل إلى المظهر الفاتح',

    // -- home / hero -------------------------------------------------------
    'hero.eyebrow'         => ':places مكاناً · :muns بلدية',
    'hero.title'           => 'كم من قطر رأيته فعلاً؟',
    'hero.sub'             => 'معظم من يعيش هنا يبقى داخل دائرة لا تتجاوز عشرين دقيقة. علّم ما زرته فعلاً، واكتشف كم بقي.',
    'hero.cta'             => 'ابدأ التعليم',
    'hero.secondary'       => 'تصفّح الأماكن أولاً',

    // -- map ---------------------------------------------------------------
    'map.title'            => 'خريطتك في قطر',
    'map.hint'             => 'اضغط على بلدية في الخريطة، أو على مكان في القائمة.',
    'map.progress'         => ':done من :total مكاناً',
    'map.mun_progress'     => ':done من :total بلدية',
    'map.search'           => 'ابحث عن مكان…',
    'map.select_all'       => 'علّم الكل',
    'map.clear_all'        => 'امسح الكل',
    'map.show_labels'      => 'إظهار الأسماء',
    'map.zoom_hint'        => 'مرّر أو اضغط بإصبعين للتكبير، واسحب للتنقل',
    'map.reset_view'       => 'إعادة العرض',
    'map.no_match'         => 'لا شيء يطابق «:q».',
    'map.all_marked'       => 'علّمت الـ:total كلها. إنجاز حقيقي.',
    'map.empty'            => 'لم تعلّم شيئاً بعد.',
    'map.mark_all'         => 'علّم الـ:n كلها هنا',
    'map.loaded'           => 'تم تحميل الخريطة المشتركة.',
    'map.keep_yours'       => 'لديك خريطة بالفعل. امسحها أولاً لتحميل هذه.',
    'map.marked_one'       => 'تم تعليم :name على خريطتك.',
    'map.marked_many'      => 'تم تعليم :n من أماكن رحلتك على خريطتك.',

    // -- progress / levels -------------------------------------------------
    'level.label'          => 'المستوى',
    'level.next'           => 'بقي :n للوصول إلى :level',
    'level.max'            => 'وصلت إلى أعلى مستوى.',

    // -- badges ------------------------------------------------------------
    'badges.title'         => 'الشارات',
    'badges.earned'        => 'مكتسبة',
    'badges.locked'        => 'مقفلة',
    'badges.count'         => ':done من :total',
    'badges.streak_hint'   => 'واصل — القليل وستُفتح هذه.',

    // -- personality -------------------------------------------------------
    'personality.title'    => 'شخصيتك في السفر',
    'personality.hint'     => 'علّم أماكن أكثر وسنحدّدها لك.',

    // -- share / export ----------------------------------------------------
    'share.title'          => 'شارك خريطتك',
    'share.name_label'     => 'الاسم على البطاقة',
    'share.name_placeholder' => 'اتركه فارغاً لـ«قطري»',
    'share.theme'          => 'نمط البطاقة',
    'share.size'           => 'حجم البطاقة',
    'share.size_post'      => 'منشور',
    'share.size_story'     => 'ستوري',
    'share.size_wide'      => 'فيسبوك',
    'share.photo'          => 'أضف صورة',
    'share.photo_change'   => 'غيّر الصورة',
    'share.photo_remove'   => 'إزالة',
    'share.photo_hint'     => 'تبقى على جهازك ما لم تنشئ رابطاً.',
    'share.download_png'   => 'تحميل صورة',
    'share.download_pdf'   => 'تحميل PDF',
    'share.make_link'      => 'أنشئ رابط مشاركة',
    'share.link_ready'     => 'رابطك جاهز',
    'share.copy'           => 'انسخ الرابط',
    'share.copied'         => 'تم النسخ',
    'share.creating'       => 'جارٍ الإنشاء…',
    'share.working'        => 'جارٍ رسم بطاقتك…',
    'share.error'          => 'تعذّر إنشاء الرابط. حاول بعد قليل.',
    'share.rate_limited'   => 'هذا عدد كبير من الخرائط في ساعة واحدة. حاول بعد قليل.',
    'share.private_note'   => 'لا حاجة إلى حساب. الرابط عام لكل من يملكه.',

    // -- the exported card itself ------------------------------------------
    // الرقم الكبير على البطاقة يعرض «٢٣ / ٤٨» أصلاً، فهذا السطر يحمل النسبة بدلاً من تكرار المجموع.
    'card.pct'             => ':pct٪ من قطر',
    'card.credit'          => 'الحدود © unseenqatar.com',
    'card.anon_name'       => 'أحد المقيمين في قطر',

    // -- places guide ------------------------------------------------------
    'places.title'         => 'الأماكن',
    'places.sub'           => 'كل ما يستحق الرحلة، مع التكلفة الحقيقية.',
    'places.all_mun'       => 'كل البلديات',
    'places.all_cats'      => 'كل الفئات',
    'places.free'          => 'دخول مجاني',
    'places.hidden'        => 'الجواهر المخفية فقط',
    'places.near_me'       => 'قريب مني',
    'places.filters'       => 'الفلاتر',
    'places.clear_filters' => 'امسح الفلاتر',
    'places.results'       => ':n مكاناً',
    'places.results_one'   => 'مكان واحد',
    'places.none'          => 'لا شيء يطابق هذه الفلاتر بعد.',
    'places.sort_name'     => 'أبجدي',
    'places.sort_mun'      => 'حسب البلدية',
    'places.sort_fee'      => 'الأرخص أولاً',
    'places.sort_default'  => 'مُقترح',
    'places.sort_distance' => 'الأقرب أولاً',
    'places.search_ph'     => 'ابحث في الأماكن…',
    'places.family'        => 'مناسب للأطفال',
    'places.metro'         => 'قريب من المترو',
    'places.hidden_tag'    => 'جوهرة مخفية',
    'places.metro_tag'     => 'مترو',
    'places.showing'       => 'يُعرض :n من :total',
    'places.near_done'     => 'الأقرب إليك أولاً.',
    'places.distance'      => 'يبعد :km كم',

    // -- place detail ------------------------------------------------------
    'place.back'           => 'كل الأماكن',
    'place.best_time'      => 'أفضل وقت للزيارة',
    'place.duration'       => 'المدة',
    'place.fee'            => 'التكلفة',
    'place.tips'           => 'أخطاء يقع فيها الزوار',
    'place.about'          => 'نبذة',
    'place.map_link'       => 'افتح في خرائط جوجل',
    'place.official'       => 'الموقع الرسمي',
    'place.verified'       => 'تم التحقق :date',
    'place.mark'           => 'زرت هذا المكان',
    'place.marked'         => 'مُعلَّم كمزار',
    'place.in_mun'         => 'في :mun',
    'place.near'           => 'قرب :loc',
    'place.source'         => 'المصدر',
    'place.related'        => 'قريب من هنا',
    'place.area'           => 'المنطقة',
    'place.municipality'   => 'البلدية',
    'place.season'         => 'الموسم',
    'place.family'         => 'مناسب للأطفال',
    'place.metro'          => 'قريب من المترو',
    'place.indoor'         => 'داخلي',
    'place.yes'            => 'نعم',
    'place.open_map'       => 'شاهده على الخريطة',
    'place.open_map_hint'  => 'يفتح الخريطة وهذا المكان معلَّم عليها.',

    // -- my trip (the "want to go" list) -----------------------------------
    // منفصلة عن علامات الخريطة عن قصد: «زرتُ هذا» و«أريد زيارته» سؤالان
    // مختلفان، ولا يصح أن يُقرأ أحدهما مكان الآخر.
    'nav.trip'             => 'رحلتي',
    'trip.title'           => 'رحلتي',
    'trip.sub'             => 'ما تريد أن تراه، منفصلاً عمّا زرته بالفعل.',
    'trip.add'             => 'أريد زيارته',
    'trip.added'           => 'في رحلتك',
    'trip.add_one'         => ':name أُضيف إلى رحلتك.',
    'trip.remove_one'      => ':name أُزيل من رحلتك.',
    'trip.add_aria'        => 'أضف :name إلى رحلتي',
    'trip.remove_aria'     => 'أزل :name من رحلتي',
    'trip.empty'           => 'لا شيء في رحلتك بعد.',
    'trip.empty_hint'      => 'اضغط «أريد زيارته» على أي مكان في الدليل وسيظهر هنا.',
    'trip.empty_cta'       => 'تصفّح الأماكن',
    'trip.empty_plans'     => 'ابدأ من خطة جاهزة',
    'trip.total'           => 'الوقت في هذه الأماكن',
    'trip.total_none'      => 'لا توجد مدد مسجّلة لهذه الأماكن',
    'trip.unknown'         => ':n بلا مدة مسجّلة',
    'trip.travel_note'     => 'هذا وقت يُقضى في الأماكن نفسها، دون حساب التنقّل بينها.',
    'trip.copy'            => 'انسخه كنص',
    'trip.copied'          => 'تم النسخ',
    'trip.copy_failed'     => 'تعذّر النسخ — حدّد النص يدوياً.',
    'trip.copy_head'       => 'رحلتي في قطر — :n مكاناً',
    'trip.copy_total'      => 'نحو :time داخل الأماكن نفسها.',
    'trip.copy_gone'       => ':n لم تعد في الدليل',
    'trip.clear'           => 'امسح الرحلة',
    'trip.clear_confirm'   => 'أإزالة كل الأماكن من رحلتك؟',
    'trip.count'           => ':n مكاناً',
    'trip.count_one'       => 'مكان واحد',
    'trip.gone'            => 'لم يعد في الدليل',
    'trip.gone_note'       => ':n من أماكن رحلتك لم تعد في الدليل. أزلها من الأسفل.',
    'trip.remove'          => 'إزالة',
    'trip.open'            => 'افتح',
    'trip.on_device'       => 'محفوظة في هذا المتصفح وحده — بلا حساب، ولا يُرسل شيء إلى أي خادم. ومسح بيانات المتصفح يمسحها.',
    'trip.needs_js'        => 'رحلتك محفوظة في هذا المتصفح لا على الخادم، فهذه الصفحة تحتاج جافاسكربت لعرضها. أما الأماكن نفسها فكلها في الدليل.',
    'trip.loading'         => 'جارٍ قراءة رحلتك…',
    'trip.see_map'         => 'علّمها على الخريطة',

    // -- ready-made plans --------------------------------------------------
    //স্তর ৩ item ৮. الأرقام تُكتب بالأرقام العربية عبر num()، فلا تُكتب هنا.
    'nav.plans'            => 'الخطط',
    'plans.title'          => 'خطط جاهزة',
    'plans.sub'            => 'يوم أو نهاية أسبوع أو توقف طويل — مرتّبة سلفاً، وبمسافات قيادة معقولة.',
    'plans.see_all'        => 'كل الخطط',
    'plans.length'         => 'المدة',
    'plans.who'            => 'لمن',
    'plans.clear'          => 'إلغاء',
    'plans.count'          => ':n خطة',
    'plans.count_one'      => 'خطة واحدة',
    'plans.showing'        => 'عرض :n من :total',
    'plans.none'           => 'لا توجد خطط مطابقة حتى الآن.',
    'plans.stops'          => ':n محطة',
    'plans.stops_one'      => 'محطة واحدة',
    'plans.about_time'     => 'نحو :time',
    'plans.empty_h'        => 'لا توجد خطط منشورة بعد',
    'plans.empty_p'        => 'تُكتب الخطط في لوحة التحكم وتُقرأ من قاعدة البيانات، ولا يوجد منشور منها في الوقت الحالي.',
    'plans.empty_where'    => 'أضفها من قسم «الخطط» في اللوحة؛ وما يبقى مسودةً لا يظهر هنا.',

    // وحدتا الطول، وتُستعملان أسفل الرقم في البطاقة.
    'itin.unit_days'       => 'أيام',
    'itin.unit_hours'      => 'ساعات',
    'itin.day_one'         => 'يوم واحد',
    'itin.days'            => ':n أيام',
    'itin.hour_one'        => 'ساعة واحدة',
    'itin.hours'           => ':n ساعات',

    // -- one plan ----------------------------------------------------------
    'plan.back'            => 'كل الخطط',
    'plan.day'             => 'اليوم :n',
    'plan.route'           => 'المسار',
    'plan.no_stops'        => 'لم تُكتب محطات هذه الخطة بعد.',
    'plan.f_length'        => 'المدة',
    'plan.f_for'           => 'الأنسب لـ',
    'plan.f_budget'        => 'الميزانية',
    'plan.f_stops'         => 'المحطات',
    'plan.f_visiting'      => 'الوقت في المحطات',
    'plan.about_time'      => 'نحو :time في المحطات',
    'plan.unknown_time'    => ':n منها بلا وقت مسجَّل، فالمجموع أعلاه جزء من اليوم لا اليوم كله.',
    'plan.verified'        => 'روجعت التفاصيل في :date',
    'plan.add_all'         => 'أضف الخطة كاملة إلى رحلتي',
    'plan.added_all'       => 'الخطة كاملة في رحلتك',
    'plan.added_n'         => 'أُضيفت :n محطات إلى رحلتك.',
    'plan.already'         => 'كل المحطات في رحلتك بالفعل.',
    'plan.open_map'        => 'اعرض المسار على الخريطة',
    'plan.open_map_hint'   => 'يعلّم كل منطقة تمر بها الخطة، لترى شكل اليوم قبل أن تلتزم به.',

    // -- who a plan is for -------------------------------------------------
    'who.any'              => 'للجميع',
    'who.family'           => 'للعائلات',
    'who.couple'           => 'للأزواج',
    'who.budget'           => 'بميزانية محدودة',
    'who.adventure'        => 'للمغامرين',
    'who.solo'             => 'للمسافرين منفردين',

    // -- visitor guide -----------------------------------------------------
    'guide.title'          => 'دليل الزائر',
    'guide.sub'            => 'ما لا يخبرك به أحد قبل وصولك.',
    'guide.toc'            => 'في هذه الصفحة',
    'guide.note_title'     => 'قبل أن تعتمد على أي مما يلي',
    'guide.note'           => 'المواعيد والأسعار والأنظمة في قطر تتغيّر كثيراً، وهذا الدليل نقطة بداية لا جدول مواعيد. تحقّق من المكان أو الجهة المشغّلة قبل انطلاقك.',
    'guide.places_cta'     => 'تصفّح دليل الأماكن',

    // -- locality kinds ----------------------------------------------------
    'kind.city'            => 'مدينة',
    'kind.town'            => 'بلدة',
    'kind.district'        => 'منطقة',
    'kind.landmark'        => 'معلم',
    'kind.industrial'      => 'صناعي',
    'kind.natural'         => 'طبيعة',

    // -- cost levels -------------------------------------------------------
    'fee.free'             => 'مجاني',
    'fee.low'              => 'أقل من ٥٠ ر.ق',
    'fee.medium'           => '٥٠–١٥٠ ر.ق',
    'fee.high'             => 'أكثر من ١٥٠ ر.ق',

    // -- units -------------------------------------------------------------
    'unit.min'             => 'د',
    'unit.hr'              => 'س',
    'unit.km'              => 'كم',
    'unit.half_hour'       => '٣٠ دقيقة',

    // -- seasons -----------------------------------------------------------
    'season.all'           => 'طوال السنة',
    'season.winter'        => 'الشتاء فقط',
    'season.summer'        => 'داخلي / الصيف',

    // -- shared map page ---------------------------------------------------
    'sm.title'             => 'خريطة :name في قطر',
    'sm.title_anon'        => 'خريطة قطر',
    'sm.progress'          => ':done من :total مكاناً · :pct٪',
    'sm.cta'               => 'أنشئ خريطتك',
    'sm.cta_sub'           => 'يستغرق دقيقة تقريباً، ولا يحتاج إلى حساب.',
    'sm.views'             => ':n مشاهدة',
    'sm.not_found'         => 'رابط الخريطة غير موجود أو تم حذفه.',
    'sm.cta_btn'           => 'ابدأ خريطتك',
    'sm.start_from'        => 'ابدأ من هذه الخريطة',

    // -- geolocation -------------------------------------------------------
    'geo.denied'           => 'تم رفض إذن الموقع.',
    'geo.unavailable'      => 'تعذّر على المتصفح تحديد موقعك.',
    'geo.unsupported'      => 'هذا المتصفح لا يدعم تحديد الموقع.',
    'geo.locating'         => 'جارٍ تحديد موقعك…',

    // -- footer ------------------------------------------------------------
    'footer.disclaimer'    => 'التفاصيل تتغيّر. تحقّق من المصادر الرسمية قبل السفر.',
    'footer.credit'        => 'الحدود من Simplemaps.',
    'footer.made'          => 'صُنع في قطر.',

    // -- errors ------------------------------------------------------------
    'err.404'              => 'هذه الصفحة غير موجودة.',
    'err.back'             => 'العودة إلى الخريطة',
];
