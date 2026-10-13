<?php
/**
 * Visitor guide content.
 *
 * Kept here rather than in inc/lang/ because it is prose, not UI chrome: the
 * language files are for strings the templates compose, and a screenful of
 * paragraph text does not belong in a flat key-value list.
 *
 * A note on how this is written. Anything that changes on its own — opening
 * hours, fares, visa rules — is described as a band and pointed at the authority
 * that actually knows, instead of being stated as a fact with a date on it. A
 * guide that confidently says "the metro runs until 11pm" is wrong within a
 * season, and worse than saying "check the current timings". Things that do not
 * move (999 is the emergency number, 240V type-G plugs, Fri–Sat weekend) are
 * stated plainly.
 *
 * Shape of a section:
 *   slug, icon, title_en/ar, lede_en/ar, items[]
 *   each item: t_en/t_ar (the claim, short) and b_en/b_ar (why or how).
 */

declare(strict_types=1);

return [

    // -----------------------------------------------------------------------
    [
        'slug'     => 'when',
        'icon'     => '🌤️',
        'title_en' => 'When to come',
        'title_ar' => 'متى تزور',
        'lede_en'  => 'The window that matters is November to March. Everything else here is a workaround.',
        'lede_ar'  => 'النافذة المهمة هي من نوفمبر إلى مارس، وما عدا ذلك ترتيبات للتعامل مع الحرارة.',
        'items'    => [
            [
                't_en' => 'Winter is the season',
                't_ar' => 'الشتاء هو الموسم',
                'b_en' => 'Days sit around 20–28°C and the evenings are cool enough to want a layer from December to February. This is when you can actually walk a corniche, camp at Sealine or eat outside in Katara.',
                'b_ar' => 'الحرارة نهاراً بين ٢٠ و٢٨ درجة، والأمسيات باردة بما يكفي لتحتاج رداءً من ديسمبر إلى فبراير. في هذه الفترة يمكنك فعلاً المشي على الكورنيش أو التخييم في سيلين أو الأكل في الهواء الطلق في كتارا.',
            ],
            [
                't_en' => 'Summer is genuinely dangerous, not just unpleasant',
                't_ar' => 'الصيف خطير فعلاً، لا مجرد مزعج',
                'b_en' => 'June to September runs past 40°C, and the coast adds humidity on top. A twenty-minute walk at midday is enough to make a healthy adult unwell. Plan indoors, or between dusk and about 9am.',
                'b_ar' => 'من يونيو إلى سبتمبر تتجاوز الحرارة ٤٠ درجة، وتضيف السواحل رطوبة فوق ذلك. المشي عشرين دقيقة في الظهيرة كافٍ لإيذاء شخص بالغ سليم. خطّط لأماكن داخلية أو للفترة بين الغروب والتاسعة صباحاً.',
            ],
            [
                't_en' => 'April, May and October are the compromise',
                't_ar' => 'أبريل ومايو وأكتوبر حل وسط',
                'b_en' => 'Hot, but the mornings and evenings are usable and the sea is at its best. Build the day around the two cool ends of it and you will be fine.',
                'b_ar' => 'حارّة، لكن الصباح والأمسيات صالحان والبحر في أفضل حال. رتّب يومك حول طرفي النهار وستكون بخير.',
            ],
            [
                't_en' => 'Ramadan shifts the whole day',
                't_ar' => 'رمضان يغيّر إيقاع اليوم كله',
                'b_en' => 'Many restaurants close in daylight, hours move late, and much of the city eats after sunset. It is a good month to visit for the atmosphere and a bad one for a beach holiday.',
                'b_ar' => 'تغلق مطاعم كثيرة نهاراً، وتتأخر المواعيد، ويتناول معظم الناس إفطارهم بعد الغروب. شهر جميل للزيارة من ناحية الأجواء، وغير مناسب لعطلة شاطئية.',
            ],
            [
                't_en' => 'The weekend is Friday and Saturday',
                't_ar' => 'نهاية الأسبوع هي الجمعة والسبت',
                'b_en' => 'Friday morning is the quietest the city gets. Many places open in the afternoon instead, so a Friday-morning plan needs checking first.',
                'b_ar' => 'صباح الجمعة هو أهدأ وقت في المدينة. كثير من الأماكن يفتح بعد الظهر، لذا تحقّق قبل أن تخطّط لصباح الجمعة.',
            ],
        ],
    ],

    // -----------------------------------------------------------------------
    [
        'slug'     => 'getting-around',
        'icon'     => '🚇',
        'title_en' => 'Getting around',
        'title_ar' => 'التنقّل',
        'lede_en'  => 'The metro covers the tourist core well. Almost everything else assumes a car.',
        'lede_ar'  => 'يغطي المترو قلب المدينة السياحي جيداً، وما عدا ذلك يفترض وجود سيارة.',
        'items'    => [
            [
                't_en' => 'The metro is the easy answer inside Doha',
                't_ar' => 'المترو هو الحل الأسهل داخل الدوحة',
                'b_en' => 'The Red line runs from the airport through Msheireb out to Al Wakrah, with a branch to Lusail. Gold and Green lines meet at Msheireb, which is the interchange you will use most. Fares are low and there is a day pass — check the operator for current times and prices, since they change.',
                'b_ar' => 'يمتد الخط الأحمر من المطار عبر مشيرب إلى الوكرة، مع فرع إلى لوسيل. يلتقي الخطان الذهبي والأخضر في مشيرب، وهو التحويل الذي ستستخدمه أكثر من غيره. الأسعار منخفضة وتوجد بطاقة يومية — راجع الجهة المشغّلة للمواعيد والأسعار الحالية لأنها تتغيّر.',
            ],
            [
                't_en' => 'Karwa taxis and the ride apps both work',
                't_ar' => 'تاكسي كروة وتطبيقات النقل تعملان',
                'b_en' => 'Karwa metered taxis are plentiful at malls and the airport. Careem and Uber are usually more predictable late at night, when it is hard to flag anything down.',
                'b_ar' => 'تتوفر تاكسيات كروة بعدّاد عند المجمعات والمطار. أما كريم وأوبر فعادة أدقّ في وقت متأخر من الليل حيث يصعب إيقاف سيارة.',
            ],
            [
                't_en' => 'Driving is on the right, and radars are everywhere',
                't_ar' => 'القيادة على اليمين والرادارات في كل مكان',
                'b_en' => 'Big roundabouts, fast ring roads, and speed cameras that do issue fines. Fuel is cheap. If you are not used to driving here, the first day on Salwa Road will teach you more than any advice.',
                'b_ar' => 'دوّارات كبيرة وطرق دائرية سريعة ورادارات تُحرّر مخالفات فعلاً. الوقود رخيص. وإن لم تكن معتاداً على القيادة هنا، فسيعلمك يومك الأول على طريق سلوى أكثر من أي نصيحة.',
            ],
            [
                't_en' => 'Souq Waqif, Msheireb and the Corniche are one walk',
                't_ar' => 'سوق واقف ومشيرب والكورنيش مشية واحدة',
                'b_en' => 'These three connect on foot in about twenty minutes, and the metro ties them together if the heat says no.',
                'b_ar' => 'هذه الثلاثة متصلة سيراً في نحو عشرين دقيقة، ويربطها المترو إن كانت الحرارة لا تسمح.',
            ],
            [
                't_en' => 'Everywhere worth the drive needs a car',
                't_ar' => 'كل مكان يستحق الرحلة يحتاج سيارة',
                'b_en' => 'Al Zubarah, Zekreet, the inland sea, the north coast beaches: no useful public transport reaches them. Rent for the day, or join a tour.',
                'b_ar' => 'الزبارة، زكريت، البحر الداخلي، وشواطئ الساحل الشمالي: لا يصل إليها نقل عام مفيد. استأجر سيارة ليوم أو انضم إلى رحلة منظّمة.',
            ],
        ],
    ],

    // -----------------------------------------------------------------------
    [
        'slug'     => 'costs',
        'icon'     => '💸',
        'title_en' => 'What things cost',
        'title_ar' => 'التكاليف',
        'lede_en'  => 'Food, fuel and transport are cheap. Hotels and alcohol are not.',
        'lede_ar'  => 'الطعام والوقود والمواصلات رخيصة، أما الفنادق والكحول فلا.',
        'items'    => [
            [
                't_en' => 'The riyal is pegged at roughly 3.64 to the dollar',
                't_ar' => 'الريال مربوط عند نحو ٣٫٦٤ للدولار',
                'b_en' => 'So mental arithmetic from dollars or euros is stable and easy. Dollars are accepted in many places, but change comes back in riyals.',
                'b_ar' => 'لذا الحساب الذهني من الدولار أو اليورو ثابت وسهل. يُقبل الدولار في أماكن كثيرة لكن الباقي يعود بالريال.',
            ],
            [
                't_en' => 'Much of the best of Qatar is free',
                't_ar' => 'الكثير من أجمل ما في قطر مجاني',
                'b_en' => 'The Corniche, wandering Souq Waqif, Katara, Aspire Park, Al Bidda Park, the MIA park and its skyline view, the beach at Sealine if you have a car. The expensive day out is optional, not compulsory.',
                'b_ar' => 'الكورنيش، والتجوّل في سوق واقف، وكتارا، وحديقة أسباير، وحديقة البدع، وحديقة متحف الفن الإسلامي وإطلالتها على الأفق، وشاطئ سيلين إن كان لديك سيارة. اليوم المكلف خيار لا فرض.',
            ],
            [
                't_en' => 'Museums charge a modest ticket',
                't_ar' => 'تتقاضى المتاحف تذكرة معتدلة',
                'b_en' => 'The Museum of Islamic Art and the National Museum of Qatar both sit in the "cheap for what it is" band, and both have free or reduced days. Check each museum\'s own site for current pricing.',
                'b_ar' => 'متحف الفن الإسلامي ومتحف قطر الوطني كلاهما في نطاق «رخيص مقابل قيمته»، ولكل منهما أيام مجانية أو مخفّضة. راجع موقع كل متحف للأسعار الحالية.',
            ],
            [
                't_en' => 'Tipping is 10% and only when it is not already added',
                't_ar' => 'الإكرامية ١٠٪ وفقط إن لم تُضف أصلاً',
                'b_en' => 'Larger restaurants often put a service charge on the bill. In taxis, rounding up is normal and sufficient.',
                'b_ar' => 'تضيف المطاعم الكبيرة غالباً رسوم خدمة على الفاتورة. وفي التاكسي يكفي تقريب المبلغ.',
            ],
            [
                't_en' => 'Alcohol is a hotel-and-licence business',
                't_ar' => 'الكحول مقصور على الفنادق والمنافذ المرخّصة',
                'b_en' => 'Served in licensed hotel bars and some restaurants, at prices that surprise people. It is not a corner-shop purchase.',
                'b_ar' => 'يُقدَّم في بارات الفنادق المرخّصة وبعض المطاعم وبأسعار تفاجئ الزائر. وهو ليس سلعة تُشترى من بقالة.',
            ],
        ],
    ],

    // -----------------------------------------------------------------------
    [
        'slug'     => 'dress',
        'icon'     => '👕',
        'title_en' => 'What to wear',
        'title_ar' => 'اللباس',
        'lede_en'  => 'Modest and loose beats stylish and tight, for comfort as much as for manners.',
        'lede_ar'  => 'المحتشم والواسع أفضل من الأنيق الضيّق، للراحة قبل الأدب.',
        'items'    => [
            [
                't_en' => 'Shoulders and knees covered in public buildings',
                't_ar' => 'تغطية الكتفين والركبتين في الأماكن العامة',
                'b_en' => 'Malls, souqs and government buildings are family spaces and the expectation is straightforward. On the beach or at a hotel pool, normal swimwear is fine.',
                'b_ar' => 'المجمعات والأسواق والمباني الحكومية أماكن عائلية والتوقّع واضح. على الشاطئ أو في مسبح فندق، لباس السباحة المعتاد مقبول.',
            ],
            [
                't_en' => 'A light scarf earns its place in the bag',
                't_ar' => 'وشاح خفيف يستحق مكانه في الحقيبة',
                'b_en' => 'Useful for entering a mosque, and genuinely useful against the sun the rest of the time.',
                'b_ar' => 'مفيد لدخول مسجد، ومفيد فعلاً ضد الشمس في بقية الأوقات.',
            ],
            [
                't_en' => 'Carry a layer in winter, including for the desert',
                't_ar' => 'احمل رداءً في الشتاء، حتى للصحراء',
                'b_en' => 'Desert nights in January are cold, not merely cool, and the temperature drops fast once the sun is down. This catches out almost every first-time camper.',
                'b_ar' => 'ليالي الصحراء في يناير باردة، لا مجرد منعشة، والحرارة تهبط بسرعة بعد الغروب. وهذا ما يفاجئ كل مخيّم لأول مرة تقريباً.',
            ],
        ],
    ],

    // -----------------------------------------------------------------------
    [
        'slug'     => 'rules',
        'icon'     => '⚖️',
        'title_en' => 'Rules worth knowing',
        'title_ar' => 'أنظمة يُستحسن معرفتها',
        'lede_en'  => 'Qatar is a conservative country and the law is enforced. None of this is hard to follow; it is just worth knowing in advance.',
        'lede_ar'  => 'قطر بلد محافظ والقانون مُطبَّق. لا شيء من هذا صعب الالتزام به، لكن معرفته مقدماً مفيدة.',
        'items'    => [
            [
                't_en' => 'Do not photograph government, military or security sites',
                't_ar' => 'لا تصوّر المواقع الحكومية أو العسكرية أو الأمنية',
                'b_en' => 'That includes police, checkpoints and airport security areas. For people, ask first — most will say yes, and being asked is appreciated.',
                'b_ar' => 'ويشمل ذلك الشرطة ونقاط التفتيش ومناطق أمن المطار. أما الأشخاص فاسأل أولاً — الأغلبية توافق، والسؤال نفسه محل تقدير.',
            ],
            [
                't_en' => 'Drinking and driving is treated with zero tolerance',
                't_ar' => 'القيادة تحت تأثير الكحول لا تُتسامح إطلاقاً',
                'b_en' => 'As is public drunkenness, and any substance offence. The consequences are serious and not negotiable at the roadside.',
                'b_ar' => 'وكذلك السُكر في الأماكن العامة وأي جريمة متعلقة بالمواد المحظورة. العواقب جدية وغير قابلة للتفاوض.',
            ],
            [
                't_en' => 'Some everyday medicines are controlled here',
                't_ar' => 'بعض الأدوية الشائعة مقيّدة هنا',
                'b_en' => 'A handful of prescription and over-the-counter drugs that are ordinary elsewhere are not allowed in. Check the list published by the health authority before you fly, and carry prescriptions for anything you do bring.',
                'b_ar' => 'عدد من الأدوية بوصفة أو بدونها والمألوفة في بلدان أخرى غير مسموح بإدخالها. راجع القائمة التي تنشرها الجهة الصحية قبل السفر، واحمل الوصفات لأي دواء تُدخله.',
            ],
            [
                't_en' => 'During Ramadan, do not eat or drink in public in daylight',
                't_ar' => 'في رمضان، لا تأكل أو تشرب علناً نهاراً',
                'b_en' => 'Most places will not confront you, and many hotels keep a screened area open for guests. It is still the one thing visitors most often get wrong.',
                'b_ar' => 'لن يواجهك أحد في الغالب، وتُبقي فنادق كثيرة منطقة محجوبة مفتوحة للنزلاء. ومع ذلك هو أكثر ما يخطئ فيه الزوار.',
            ],
            [
                't_en' => 'Keep public behaviour low-key',
                't_ar' => 'اجعل سلوكك العام هادئاً',
                'b_en' => 'Loud argument, heavy affection and aggressive driving all draw attention here in a way they would not elsewhere. Thanking people and patience at queues go a very long way.',
                'b_ar' => 'الجدال بصوت عالٍ والإفراط في المشاعر والقيادة العدوانية كلها تلفت الأنظار هنا بشكل مختلف. والشكر والصبر في الطوابير يصنعان فرقاً كبيراً.',
            ],
        ],
    ],

    // -----------------------------------------------------------------------
    [
        'slug'     => 'heat',
        'icon'     => '🌡️',
        'title_en' => 'Heat and the desert',
        'title_ar' => 'الحرارة والصحراء',
        'lede_en'  => 'This is the section that matters most, because the failure mode here is not a spoiled afternoon.',
        'lede_ar'  => 'هذا أهم قسم، لأن الخطأ هنا لا يعني مجرد يوم فاشل.',
        'items'    => [
            [
                't_en' => 'Carry water all year, not just in summer',
                't_ar' => 'احمل الماء طوال السنة لا في الصيف فقط',
                'b_en' => 'Heat exhaustion comes on faster than people expect, and coastal humidity stops sweat from doing its job. If you stop sweating while hot, that is an emergency.',
                'b_ar' => 'الإجهاد الحراري يبدأ أسرع مما يتوقّع الناس، ورطوبة الساحل تمنع التعرّق من أداء وظيفته. إن توقّفت عن التعرّق وأنت حارّ فهذه حالة طارئة.',
            ],
            [
                't_en' => 'Never leave a child or an animal in a parked car',
                't_ar' => 'لا تترك طفلاً أو حيواناً في سيارة متوقفة',
                'b_en' => 'Even in winter, and even for "two minutes". Interiors pass 60°C within minutes in the sun here.',
                'b_ar' => 'حتى في الشتاء، وحتى «لدقيقتين». تتجاوز حرارة المقصورة ٦٠ درجة خلال دقائق تحت الشمس هنا.',
            ],
            [
                't_en' => 'The wild beaches have no lifeguards',
                't_ar' => 'الشواطئ البرّية بلا منقذين',
                'b_en' => 'Sealine and the beaches past it have currents and sudden drop-offs. People drown here every year, usually strong swimmers who did not respect the water.',
                'b_ar' => 'سيلين والشواطئ بعدها فيها تيارات وانحدارات مفاجئة. يغرق فيها أشخاص كل عام، وغالباً سبّاحون جيدون لم يحترموا الماء.',
            ],
            [
                't_en' => 'Never go off-road into the desert alone',
                't_ar' => 'لا تدخل الصحراء وحدك بسيارة',
                'b_en' => 'Tell someone your route and expected return. Take fuel, water, a charged phone and a real spare. Cars sink in the sabkha around the inland sea every season — it is soft, and it does not look it.',
                'b_ar' => 'أخبر أحداً بمسارك ووقت عودتك المتوقع. خُذ وقوداً وماءً وهاتفاً مشحوناً وإطاراً احتياطياً حقيقياً. تغوص السيارات في سبخة البحر الداخلي كل موسم — فهي رخوة ولا تبدو كذلك.',
            ],
            [
                't_en' => 'Winter sun still burns',
                't_ar' => 'شمس الشتاء تحرق أيضاً',
                'b_en' => 'Sunscreen, sunglasses and a hat are not beach-only kit. The UV here is high year-round and the breeze stops you noticing.',
                'b_ar' => 'الواقي الشمسي والنظارات والقبعة ليست عدّة شاطئ فقط. الأشعة فوق البنفسجية عالية طوال السنة والنسيم يمنعك من ملاحظة ذلك.',
            ],
        ],
    ],

    // -----------------------------------------------------------------------
    [
        'slug'     => 'family',
        'icon'     => '🧒',
        'title_en' => 'With children',
        'title_ar' => 'مع الأطفال',
        'lede_en'  => 'Qatar is unusually easy with kids, as long as you avoid midday.',
        'lede_ar'  => 'قطر سهلة مع الأطفال بشكل ملحوظ، شرط تجنّب الظهيرة.',
        'items'    => [
            [
                't_en' => 'The malls are built around families',
                't_ar' => 'المجمعات مبنية حول العائلات',
                'b_en' => 'Villaggio, Doha Festival City, Mall of Qatar and City Center all have large play areas, cinemas and food courts, and they are the standard summer answer.',
                'b_ar' => 'فياجيو، وفستيفال سيتي الدوحة، ومول قطر، وسيتي سنتر: لكل منها مناطق لعب كبيرة ودور سينما ومطاعم، وهي الحل المعتاد في الصيف.',
            ],
            [
                't_en' => 'Green space is free and good',
                't_ar' => 'المساحات الخضراء مجانية وجيدة',
                'b_en' => 'Aspire Park, Al Bidda Park, the MIA park and Katara all have space to run and shade to sit in, plus coffee within reach.',
                'b_ar' => 'حديقة أسباير، وحديقة البدع، وحديقة متحف الفن الإسلامي، وكتارا: كلها فيها مساحة للجري وظل للجلوس وقهوة قريبة.',
            ],
            [
                't_en' => 'The National Museum of Qatar works for children',
                't_ar' => 'متحف قطر الوطني مناسب للأطفال',
                'b_en' => 'Its galleries are hands-on and film-led. The Museum of Islamic Art is quieter and rewards older children more.',
                'b_ar' => 'قاعاته تفاعلية وتعتمد على الأفلام. أما متحف الفن الإسلامي فأهدأ ويفيد الأطفال الأكبر سناً أكثر.',
            ],
            [
                't_en' => 'Go out early or after four',
                't_ar' => 'اخرج مبكراً أو بعد الرابعة',
                'b_en' => 'Between roughly 11am and 3pm in summer, outdoor play is not worth the risk. Every family here lives by this and you should too.',
                'b_ar' => 'بين الحادية عشرة والثالثة تقريباً في الصيف، اللعب في الخارج لا يستحق المخاطرة. كل عائلة هنا تعيش على هذا الإيقاع، وافعل مثله.',
            ],
        ],
    ],

    // -----------------------------------------------------------------------
    [
        'slug'     => 'practical',
        'icon'     => '🧭',
        'title_en' => 'Practical details',
        'title_ar' => 'معلومات عملية',
        'lede_en'  => 'The small things that decide whether the first day goes smoothly.',
        'lede_ar'  => 'التفاصيل الصغيرة التي تحدّد إن كان يومك الأول سلساً.',
        'items'    => [
            [
                't_en' => 'Emergency number: 999',
                't_ar' => 'رقم الطوارئ: ٩٩٩',
                'b_en' => 'One number for police, ambulance and fire.',
                'b_ar' => 'رقم واحد للشرطة والإسعاف والإطفاء.',
            ],
            [
                't_en' => 'SIM cards at the airport, passport required',
                't_ar' => 'شرائح الاتصال في المطار بجواز السفر',
                'b_en' => 'Ooredoo and Vodafone both have arrival counters at Hamad International. Tourist plans are cheap and data is generous.',
                'b_ar' => 'لأوريدو وفودافون منافذ في مطار حمد الدولي. باقات الزوار رخيصة والبيانات سخية.',
            ],
            [
                't_en' => '240V, UK-style three-pin plugs',
                't_ar' => '٢٤٠ فولت ومقابس بريطانية ثلاثية',
                'b_en' => 'Type G. Bring an adapter unless your devices are already UK-plugged.',
                'b_ar' => 'من النوع G. أحضر محوّلاً إلا إن كانت أجهزتك بمقابس بريطانية أصلاً.',
            ],
            [
                't_en' => 'English works everywhere',
                't_ar' => 'الإنجليزية تعمل في كل مكان',
                'b_en' => 'Arabic is the official language, but signs are bilingual and English is the common language of the city. Hindi, Urdu, Malayalam, Tagalog and Nepali are all widely spoken too.',
                'b_ar' => 'العربية هي اللغة الرسمية، لكن اللافتات ثنائية اللغة والإنجليزية هي لغة المدينة المشتركة. كما تُستخدم الهندية والأردية والمالايالامية والتاغالوغية والنيبالية على نطاق واسع.',
            ],
            [
                't_en' => 'Check your own visa rule before booking',
                't_ar' => 'تحقّق من قاعدة التأشيرة الخاصة بجنسيتك قبل الحجز',
                'b_en' => 'Many nationalities get a free visa on arrival or an online visa through the official Hayya platform, but the list is revised and depends on your passport. Look it up on the government site rather than trusting a blog, including this one.',
                'b_ar' => 'تحصل جنسيات كثيرة على تأشيرة مجانية عند الوصول أو تأشيرة إلكترونية عبر منصة «هيا» الرسمية، لكن القائمة تُحدَّث وتعتمد على جواز سفرك. راجع الموقع الحكومي لا مدوّنة، بما في ذلك هذه.',
            ],
            [
                't_en' => 'Pharmacies are everywhere; travel insurance is still worth it',
                't_ar' => 'الصيدليات في كل مكان، والتأمين على السفر يستحق مع ذلك',
                'b_en' => 'Public hospitals run 24-hour emergency departments and care is good. Insurance matters for anything that turns into a real bill.',
                'b_ar' => 'تعمل المستشفيات الحكومية بأقسام طوارئ على مدار الساعة والرعاية جيدة. التأمين يهمّ في أي حالة تتحوّل إلى فاتورة حقيقية.',
            ],
        ],
    ],
];
