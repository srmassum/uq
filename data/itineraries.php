<?php
/**
 * Ready-made plans — whole days somebody can take without planning anything.
 *
 * This is the content behind স্তর ৩ item ৮: the 1/2/3-day trips and the 8/12/24-hour
 * stopovers, for families, couples, people on a budget and people who want the
 * desert. setup/prepare.php reads this file and writes sql/seed-itineraries.sql.
 *
 * A stop names a PLACE by slug — never an id, never a locality. prepare.php
 * fails the build if the slug is not in data/places.php, so a plan can never
 * point at somewhere the guide does not have. The place's own duration, fee and
 * category come along with it: nothing about a place is repeated here, which is
 * what stops a plan and the guide from disagreeing about the same afternoon.
 *
 * A stop may instead carry `text` rather than `place`, for the parts of a route
 * that are not a destination — "lunch in Al Khor", "back to the airport". `text`
 * is the whole stop and must read as a sentence, so it ends with a full stop.
 * An optional `note` after it is appended as a second sentence, because the plan
 * page prints one prose field for a text stop and nothing else. Text stops are
 * deliberately NOT counted in the plan's total — they have no recorded duration
 * to count, and counting them as zero would make a long day read as a short one.
 *
 * `day` numbers the day a stop falls on and defaults to 1. A stopover is written
 * across two days when it genuinely has two halves (arrival evening, next
 * morning) so the page can say so, even though its length is still in hours.
 *
 * `mins`-style facts are NOT written here. The totals on the plan page are added
 * up from the places themselves at render time.
 *
 * On facts: this is a plausible order of a day, not a timetable. Driving times
 * are not included in any total — the page says so — and Qatar's roads, opening
 * hours and ferry times change. `verified_at` is therefore left NULL on every
 * plan, so no plan claims its details were checked when they were not.
 */

return [

    // ======================= One day in the city ========================

    [
        'slug' => 'doha-in-a-day',
        'title_en' => 'Doha in a day', 'title_ar' => 'الدوحة في يوم',
        'intro_en' => 'The classic first day: the old market, the museum on its own island, and the walk along the water between them.',
        'intro_ar' => 'اليوم الأول الكلاسيكي: السوق القديم، والمتحف على جزيرته، والمشي بمحاذاة الماء بينهما.',
        'type' => 'days', 'value' => 1, 'who' => 'any', 'budget' => 'low',
        'stops' => [
            ['place' => 'al-koot-fort', 'time_en' => '9am', 'time_ar' => '٩ صباحاً',
             'note_en' => 'Half an hour, and it puts the rest of the day in context.',
             'note_ar' => 'نصف ساعة، وتضع بقية اليوم في سياقه.'],
            ['place' => 'souq-waqif', 'time_en' => '10am', 'time_ar' => '١٠ صباحاً',
             'note_en' => 'Coffee and the spice lanes before the heat and the crowds arrive.',
             'note_ar' => 'قهوة وأزقة البهارات قبل أن يأتي الحرّ والزحام.'],
            ['place' => 'museum-islamic-art', 'time_en' => '1pm', 'time_ar' => '١ ظهراً',
             'note_en' => 'The collection is the point; the building is the photograph.',
             'note_ar' => 'المجموعة هي المقصد، والمبنى هو الصورة.'],
            ['place' => 'mia-park', 'time_en' => '3.30pm', 'time_ar' => '٣:٣٠ عصراً',
             'note_en' => 'Shade, grass and the skyline across the water.',
             'note_ar' => 'ظلّ وعشب وأفق المدينة عبر الماء.'],
            ['place' => 'msheireb-downtown', 'time_en' => '5pm', 'time_ar' => '٥ عصراً',
             'note_en' => 'Old Doha rebuilt — a coffee and a walk, not a destination.',
             'note_ar' => 'الدوحة القديمة بعد إعادة بنائها — قهوة ومشية، لا مقصداً.'],
            ['place' => 'doha-corniche', 'time_en' => '7pm', 'time_ar' => '٧ مساءً',
             'note_en' => 'Finish on the water as the lights come on.',
             'note_ar' => 'اختم على الماء بينما تشتعل الأضواء.'],
        ],
    ],

    [
        'slug' => 'doha-with-kids',
        'title_en' => 'Doha with children', 'title_ar' => 'الدوحة مع الأطفال',
        'intro_en' => 'Four stops that survive a hot afternoon with children: two parks, a beach, and a museum built to be walked through rather than read.',
        'intro_ar' => 'أربع محطات تصمد في ظهيرة حارة مع الأطفال: حديقتان وشاطئ ومتحف بُني ليُتجوَّل فيه لا ليُقرأ.',
        'type' => 'days', 'value' => 1, 'who' => 'family', 'budget' => 'low',
        'stops' => [
            ['place' => 'mia-park', 'time_en' => '9.30am', 'time_ar' => '٩:٣٠ صباحاً',
             'note_en' => 'Start outdoors while it is still bearable, and bring water.',
             'note_ar' => 'ابدأ في الهواء الطلق ما دام الجو محتملاً، وخُذ الماء معك.'],
            ['place' => 'national-museum-qatar', 'time_en' => '11am', 'time_ar' => '١١ صباحاً',
             'note_en' => 'The building is the exhibit — the galleries are short, dark and cool, which is exactly what is needed by now.',
             'note_ar' => 'المبنى نفسه هو المعرض — القاعات قصيرة ومعتمة وباردة، وهو بالضبط ما تحتاجه في هذا الوقت.'],
            ['place' => 'katara', 'time_en' => '2pm', 'time_ar' => '٢ ظهراً',
             'note_en' => 'Lunch, then room to run between the galleries.',
             'note_ar' => 'غداء، ثم مساحة للجري بين الصالات.'],
            ['place' => 'katara-beach', 'time_en' => '4.30pm', 'time_ar' => '٤:٣٠ عصراً',
             'note_en' => 'Swim before sunset. The water is shallow and calm, which is the whole reason this one is last.',
             'note_ar' => 'اسبحوا قبل الغروب. الماء ضحل وهادئ، ولهذا جاءت هذه المحطة أخيراً.'],
        ],
    ],

    [
        'slug' => 'a-day-for-two',
        'title_en' => 'A day for two', 'title_ar' => 'يوم لشخصين',
        'intro_en' => 'Two museums at an unhurried pace, then the whole afternoon outdoors and dinner on the marina. Best from November to March.',
        'intro_ar' => 'متحفان على مهل، ثم عصر كامل في الهواء الطلق وعشاء على المرسى. الأفضل من نوفمبر إلى مارس.',
        'type' => 'days', 'value' => 1, 'who' => 'couple', 'budget' => 'medium',
        'stops' => [
            ['place' => 'museum-islamic-art', 'time_en' => '10am', 'time_ar' => '١٠ صباحاً',
             'note_en' => 'Go slowly through the upper floors, then take the terrace.',
             'note_ar' => 'تمهّل في الطوابق العليا، ثم اخرج إلى الشرفة.'],
            ['place' => 'msheireb-museums', 'time_en' => '1pm', 'time_ar' => '١ ظهراً',
             'note_en' => 'Four small houses, each one about twenty minutes.',
             'note_ar' => 'أربعة بيوت صغيرة، كل واحد نحو عشرين دقيقة.'],
            ['place' => 'katara', 'time_en' => '4pm', 'time_ar' => '٤ عصراً',
             'note_en' => 'The amphitheatre and the mosque are the two things worth the walk.',
             'note_ar' => 'المسرح الروماني والمسجد هما ما يستحق المشي.'],
            ['place' => 'the-pearl', 'time_en' => '7pm', 'time_ar' => '٧ مساءً',
             'note_en' => 'Dinner on the marina. The boats start moving after dark.',
             'note_ar' => 'عشاء على المرسى. القوارب تبدأ الحركة بعد حلول الظلام.'],
        ],
    ],

    // ======================= Out of the city ============================

    [
        'slug' => 'the-north-in-a-day',
        'title_en' => 'The north in a day', 'title_ar' => 'الشمال في يوم',
        'intro_en' => 'Al Zubarah to Al Khor with the mangroves in between. A long drive each way — leave Doha by seven.',
        'intro_ar' => 'من الزبارة إلى الخور والمنغروف بينهما. الطريق طويل في الاتجاهين — اخرج من الدوحة قبل السابعة.',
        'type' => 'days', 'value' => 1, 'who' => 'any', 'budget' => 'low',
        'stops' => [
            ['place' => 'al-zubarah-fort', 'time_en' => '9am', 'time_ar' => '٩ صباحاً',
             'note_en' => 'The fort first — the site it guards makes more sense afterwards.',
             'note_ar' => 'ابدأ بالقلعة — الموقع الذي تحرسه يصبح أوضح بعدها.'],
            ['place' => 'zubarah-town-ruins', 'time_en' => '11am', 'time_ar' => '١١ صباحاً',
             'note_en' => 'Walled, exposed and with almost no shade. Hats, and not for long.',
             'note_ar' => 'مسوَّر ومكشوف وبلا ظل تقريباً. قبعات، ولا تُطِل الوقوف.'],
            ['text_en' => 'Lunch in Al Khor.', 'text_ar' => 'غداء في الخور.',
             'time_en' => '1pm', 'time_ar' => '١ ظهراً',
             'note_en' => 'The town is the only real stop for food on this route. Nothing between here and Doha is open at lunchtime.',
             'note_ar' => 'البلدة هي المحطة الحقيقية الوحيدة للطعام على هذا المسار. لا شيء مفتوح وقت الغداء بينها وبين الدوحة.'],
            ['place' => 'al-thakhira-mangroves', 'time_en' => '2.30pm', 'time_ar' => '٢:٣٠ عصراً',
             'note_en' => 'Best on the water, so ask about a boat rather than walking the edge.',
             'note_ar' => 'الأفضل أن تكون على الماء، فاسأل عن قارب بدل المشي على الحافة.'],
            ['place' => 'al-khor-corniche', 'time_en' => '5pm', 'time_ar' => '٥ عصراً',
             'note_en' => 'Sit by the water before the drive back.',
             'note_ar' => 'اجلس عند الماء قبل طريق العودة.'],
        ],
    ],

    [
        'slug' => 'the-empty-middle',
        'title_en' => 'The empty middle', 'title_ar' => 'الوسط الخالي',
        'intro_en' => 'Zekreet, the limestone cliffs and the Dukhan road. This is the west coast, where almost nobody goes and there is nothing to buy.',
        'intro_ar' => 'الزكريت وجروف الحجر الجيري وطريق دخان. هذا هو الساحل الغربي، حيث لا يذهب أحد تقريباً ولا يوجد ما يُشترى.',
        'type' => 'days', 'value' => 1, 'who' => 'adventure', 'budget' => 'low',
        'stops' => [
            ['place' => 'zekreet-film-city', 'time_en' => '8am', 'time_ar' => '٨ صباحاً',
             'note_en' => 'A film set left to the sand. It is a ruin now, and better for it.',
             'note_ar' => 'موقع تصوير تُرك للرمل. صار أطلالاً، وهو أجمل هكذا.'],
            ['place' => 'zekreet-limestone', 'time_en' => '10am', 'time_ar' => '١٠ صباحاً',
             'note_en' => 'The formations are a short walk from where the road gives up.',
             'note_ar' => 'التشكيلات على مسافة مشي قصيرة من حيث ينتهي الطريق.'],
            ['place' => 'dukhan-escarpment', 'time_en' => '1pm', 'time_ar' => '١ ظهراً',
             'note_en' => 'The one place on this route with any height to it. Bring everything you plan to eat or drink.',
             'note_ar' => 'المكان الوحيد على هذا المسار الذي له ارتفاع. خُذ كل ما تنوي أكله أو شربه.'],
            ['place' => 'rawdat-rashed', 'time_en' => '4pm', 'time_ar' => '٤ عصراً',
             'note_en' => 'A green bowl in the middle of nothing — the surprise of the day.',
             'note_ar' => 'حوض أخضر في وسط لا شيء — مفاجأة اليوم.'],
        ],
    ],

    [
        'slug' => 'sealine-and-the-south',
        'title_en' => 'Sealine and the south', 'title_ar' => 'سيلين والجنوب',
        'intro_en' => 'The old market at Al Wakrah, then the dunes. Do not take a saloon car past the last petrol station.',
        'intro_ar' => 'السوق القديم في الوكرة، ثم الكثبان. لا تأخذ سيارة صالون بعد آخر محطة وقود.',
        'type' => 'days', 'value' => 1, 'who' => 'adventure', 'budget' => 'medium',
        'stops' => [
            ['place' => 'al-wakrah-corniche', 'time_en' => '9am', 'time_ar' => '٩ صباحاً',
             'note_en' => 'The dhow harbour is the reason to come here rather than straight to the sand.',
             'note_ar' => 'مرسى الداو هو سبب المجيء إلى هنا بدل الذهاب مباشرة إلى الرمل.'],
            ['place' => 'souq-al-wakra', 'time_en' => '10.30am', 'time_ar' => '١٠:٣٠ صباحاً',
             'note_en' => 'Smaller and quieter than Souq Waqif, and the better for it.',
             'note_ar' => 'أصغر وأهدأ من سوق واقف، وهذا أفضل له.'],
            ['place' => 'sealine-dunes', 'time_en' => '1pm', 'time_ar' => '١ ظهراً',
             'note_en' => 'Go with an operator unless you have a 4x4 and know the sand. Air down before you leave the tarmac, not after you are stuck.',
             'note_ar' => 'اذهب مع شركة تنظيم إلا إذا كانت لديك دفع رباعي وتعرف الرمل. خفّض ضغط الإطارات قبل مغادرة الإسفلت، لا بعد أن تغرز.'],
        ],
    ],

    // ======================= Stopovers =================================

    [
        'slug' => 'stopover-8-hours',
        'title_en' => 'An eight-hour stopover', 'title_ar' => 'توقف ٨ ساعات',
        'intro_en' => 'Eight hours between flights is a real half-day in the city, not a wait in a lounge. The metro does the work and the corniche is twenty minutes from the airport.',
        'intro_ar' => 'ثماني ساعات بين رحلتين تعني نصف يوم حقيقي في المدينة، لا انتظاراً في صالة. المترو يقوم بالمهمة والكورنيش على عشرين دقيقة من المطار.',
        'type' => 'hours', 'value' => 8, 'who' => 'any', 'budget' => 'low',
        'stops' => [
            ['place' => 'souq-waqif', 'time_en' => 'First', 'time_ar' => 'أولاً',
             'note_en' => 'Gold Line from the airport, one change at Msheireb, then walk in. Leave the suitcase in left luggage rather than dragging it through the lanes.',
             'note_ar' => 'الخط الذهبي من المطار، تبديل واحد في مشيرب، ثم امشِ. اترك الحقيبة في الأمانات بدل جرّها في الأزقة.'],
            ['place' => 'museum-islamic-art', 'time_en' => 'Then', 'time_ar' => 'ثم',
             'note_en' => 'One stop on the metro and a short walk. Two hours is enough if you keep to the upper floors.',
             'note_ar' => 'محطة واحدة بالمترو ثم مشي قصير. ساعتان تكفيان إن اقتصرت على الطوابق العليا.'],
            ['place' => 'mia-park', 'time_en' => 'Last', 'time_ar' => 'أخيراً',
             'note_en' => 'The park is next door, so it costs no time to see the skyline from the water before turning back.',
             'note_ar' => 'الحديقة بجواره، فلا يكلّفك رؤية الأفق من الماء وقتاً قبل العودة.'],
            ['text_en' => 'Back to Hamad International.', 'text_ar' => 'العودة إلى حمد الدولي.',
             'time_en' => 'Allow two hours', 'time_ar' => 'احسب ساعتين',
             'note_en' => 'The metro runs back the same way. Be at the airport two hours before departure — the queues at the desk close earlier than you expect.',
             'note_ar' => 'المترو يعود من الطريق نفسه. كن في المطار قبل الإقلاع بساعتين — تُغلق صفوف التسجيل أبكر مما تتوقع.'],
        ],
    ],

    [
        'slug' => 'stopover-12-hours',
        'title_en' => 'A twelve-hour stopover', 'title_ar' => 'توقف ١٢ ساعة',
        'intro_en' => 'Enough for the market, the museum island and the old city, with time to actually sit down in between rather than moving the whole time.',
        'intro_ar' => 'يكفي للسوق وجزيرة المتحف والمدينة القديمة، مع وقت للجلوس فعلاً بينها بدل التنقل طوال الوقت.',
        'type' => 'hours', 'value' => 12, 'who' => 'any', 'budget' => 'low',
        'stops' => [
            ['place' => 'al-koot-fort', 'time_en' => '8am', 'time_ar' => '٨ صباحاً',
             'note_en' => 'If you land early enough, this is the quietest hour the souq has.',
             'note_ar' => 'إن وصلت مبكراً بما يكفي، فهذه أهدأ ساعة يعرفها السوق.'],
            ['place' => 'souq-waqif', 'time_en' => '9am', 'time_ar' => '٩ صباحاً',
             'note_en' => 'Breakfast here rather than at the airport — it costs less and it is better.',
             'note_ar' => 'تناول الفطور هنا لا في المطار — أرخص وأطيب.'],
            ['place' => 'museum-islamic-art', 'time_en' => '12pm', 'time_ar' => '١٢ ظهراً',
             'note_en' => 'The middle of the day is the right time to be indoors.',
             'note_ar' => 'وسط النهار هو الوقت الصحيح لتكون داخل مبنى.'],
            ['place' => 'mia-park', 'time_en' => '2.30pm', 'time_ar' => '٢:٣٠ عصراً',
             'note_en' => 'Sit still for a while. This is the stop that makes the day survivable.',
             'note_ar' => 'اجلس بلا حركة قليلاً. هذه هي المحطة التي تجعل اليوم محتملاً.'],
            ['place' => 'katara', 'time_en' => '4pm', 'time_ar' => '٤ عصراً',
             'note_en' => 'Late afternoon light on the amphitheatre, then back to the airport from the same line.',
             'note_ar' => 'ضوء العصر على المسرح الروماني، ثم العودة إلى المطار من الخط نفسه.'],
        ],
    ],

    [
        'slug' => 'stopover-24-hours',
        'title_en' => 'A twenty-four-hour stopover', 'title_ar' => 'توقف ٢٤ ساعة',
        'intro_en' => 'A day and a night in the city: the old market and the museum island, dinner on the marina, then the second half in the morning.',
        'intro_ar' => 'يوم وليلة في المدينة: السوق القديم وجزيرة المتحف، وعشاء على المرسى، ثم النصف الثاني في الصباح.',
        'type' => 'hours', 'value' => 24, 'who' => 'any', 'budget' => 'medium',
        'stops' => [
            ['place' => 'al-koot-fort', 'day' => 1, 'time_en' => '9am', 'time_ar' => '٩ صباحاً',
             'note_en' => 'Start with the fort and the souq around it, while the lanes are still cool.',
             'note_ar' => 'ابدأ بالقلعة والسوق حولها، والأزقة ما زالت باردة.'],
            ['place' => 'souq-waqif', 'day' => 1, 'time_en' => '10am', 'time_ar' => '١٠ صباحاً',
             'note_en' => 'The middle of the morning is when the market is actually trading.',
             'note_ar' => 'منتصف الصباح هو وقت التجارة الحقيقية في السوق.'],
            ['place' => 'museum-islamic-art', 'day' => 1, 'time_en' => '12.30pm', 'time_ar' => '١٢:٣٠ ظهراً',
             'note_en' => 'Two hours inside, out of the heat, with the best view in the city from the terrace.',
             'note_ar' => 'ساعتان في الداخل بعيداً عن الحرّ، مع أفضل إطلالة في المدينة من الشرفة.'],
            ['place' => 'mia-park', 'day' => 1, 'time_en' => '3pm', 'time_ar' => '٣ عصراً',
             'note_en' => 'The park is next door. Give yourself the hour.',
             'note_ar' => 'الحديقة بجواره. امنح نفسك هذه الساعة.'],
            ['place' => 'the-pearl', 'day' => 1, 'time_en' => '7pm', 'time_ar' => '٧ مساءً',
             'note_en' => 'Dinner and a hotel — the marina district is where most of the hotels are, so this ends the day near a bed.',
             'note_ar' => 'عشاء وفندق — حي المرسى هو حيث معظم الفنادق، فبهذا ينتهي اليوم قرب سرير.'],
            ['place' => 'msheireb-museums', 'day' => 2, 'time_en' => '9am', 'time_ar' => '٩ صباحاً',
             'note_en' => 'The second morning: four restored houses, all short, all in one square.',
             'note_ar' => 'الصباح الثاني: أربعة بيوت مرمّمة، كلها قصيرة، وكلها في ساحة واحدة.'],
            ['place' => 'national-museum-qatar', 'day' => 2, 'time_en' => '11am', 'time_ar' => '١١ صباحاً',
             'note_en' => 'The building alone justifies the stop, and the galleries are a good last hour out of the sun.',
             'note_ar' => 'المبنى وحده يبرّر التوقف، والقاعات ساعة أخيرة جيدة بعيداً عن الشمس.'],
            ['place' => 'katara', 'day' => 2, 'time_en' => '2pm', 'time_ar' => '٢ ظهراً',
             'note_en' => 'Lunch, then the last walk before the airport. Leave three hours before departure.',
             'note_ar' => 'غداء، ثم آخر مشية قبل المطار. اخرج قبل الإقلاع بثلاث ساعات.'],
        ],
    ],

    // ======================= Longer trips ==============================

    [
        'slug' => 'two-days-first-visit',
        'title_en' => 'Two days, first visit', 'title_ar' => 'يومان لأول زيارة',
        'intro_en' => 'One day for old Doha and one for the museums and the water. This is the trip to take if you have never been here before.',
        'intro_ar' => 'يوم للدوحة القديمة ويوم للمتاحف والماء. هذه هي الرحلة إن لم تكن زرت البلاد من قبل.',
        'type' => 'days', 'value' => 2, 'who' => 'any', 'budget' => 'low',
        'stops' => [
            ['place' => 'al-koot-fort', 'day' => 1, 'time_en' => '9am', 'time_ar' => '٩ صباحاً',
             'note_en' => 'Small, free, and it explains what the souq next door grew out of.',
             'note_ar' => 'صغيرة ومجانية، وتشرح ما نما منه السوق المجاور.'],
            ['place' => 'souq-waqif', 'day' => 1, 'time_en' => '10am', 'time_ar' => '١٠ صباحاً',
             'note_en' => 'Give it a full morning. The side lanes are the point, not the main one.',
             'note_ar' => 'امنحه صباحاً كاملاً. الأزقة الجانبية هي المقصد، لا الشارع الرئيسي.'],
            ['place' => 'museum-islamic-art', 'day' => 1, 'time_en' => '1pm', 'time_ar' => '١ ظهراً',
             'note_en' => 'Indoors for the hottest hours, which is exactly what they are for.',
             'note_ar' => 'في الداخل خلال أشد الساعات حرارة، وهذا بالضبط ما وُجدت له.'],
            ['place' => 'mia-park', 'day' => 1, 'time_en' => '3.30pm', 'time_ar' => '٣:٣٠ عصراً',
             'note_en' => 'The best free hour in the city.',
             'note_ar' => 'أفضل ساعة مجانية في المدينة.'],
            ['place' => 'doha-corniche', 'day' => 1, 'time_en' => '6pm', 'time_ar' => '٦ مساءً',
             'note_en' => 'Walk it rather than drive it. The whole skyline is on this side.',
             'note_ar' => 'امشِ عليه لا تقُده. كامل أفق المدينة من هذا الجانب.'],
            ['place' => 'msheireb-museums', 'day' => 2, 'time_en' => '9am', 'time_ar' => '٩ صباحاً',
             'note_en' => 'Start the second day here — it is a short walk from most of the centre.',
             'note_ar' => 'ابدأ اليوم الثاني من هنا — فهو على مسافة مشي قصيرة من معظم الوسط.'],
            ['place' => 'national-museum-qatar', 'day' => 2, 'time_en' => '11.30am', 'time_ar' => '١١:٣٠ صباحاً',
             'note_en' => 'Book nothing after this for two hours; the galleries take longer than they look.',
             'note_ar' => 'لا تحجز شيئاً بعدها لساعتين؛ القاعات تأخذ وقتاً أطول مما تبدو.'],
            ['place' => 'katara', 'day' => 2, 'time_en' => '4pm', 'time_ar' => '٤ عصراً',
             'note_en' => 'Late afternoon, when the light is on the mosque.',
             'note_ar' => 'في العصر، حين يقع الضوء على المسجد.'],
            ['place' => 'the-pearl', 'day' => 2, 'time_en' => '7pm', 'time_ar' => '٧ مساءً',
             'note_en' => 'The last evening. Eat on the water.',
             'note_ar' => 'المساء الأخير. تناول العشاء على الماء.'],
        ],
    ],

    [
        'slug' => 'three-days-everything',
        'title_en' => 'Three days, everything', 'title_ar' => 'ثلاثة أيام لكل شيء',
        'intro_en' => 'The city, then the north, then the west. Three days is the first length of trip that gets you out of Doha and back without hurrying.',
        'intro_ar' => 'المدينة، ثم الشمال، ثم الغرب. ثلاثة أيام هي أول مدة تُخرجك من الدوحة وتعيدك بلا عجلة.',
        'type' => 'days', 'value' => 3, 'who' => 'any', 'budget' => 'medium',
        'stops' => [
            ['place' => 'al-koot-fort', 'day' => 1, 'time_en' => '9am', 'time_ar' => '٩ صباحاً',
             'note_en' => 'Day one is the city, and this is where the city starts.',
             'note_ar' => 'اليوم الأول هو المدينة، ومن هنا تبدأ المدينة.'],
            ['place' => 'souq-waqif', 'day' => 1, 'time_en' => '10am', 'time_ar' => '١٠ صباحاً',
             'note_en' => 'Morning, before the crowds and the heat arrive together.',
             'note_ar' => 'صباحاً، قبل أن يأتي الزحام والحرّ معاً.'],
            ['place' => 'museum-islamic-art', 'day' => 1, 'time_en' => '1pm', 'time_ar' => '١ ظهراً',
             'note_en' => 'The one museum on this trip that is worth three hours rather than two.',
             'note_ar' => 'المتحف الوحيد في هذه الرحلة الذي يستحق ثلاث ساعات لا ساعتين.'],
            ['place' => 'mia-park', 'day' => 1, 'time_en' => '4pm', 'time_ar' => '٤ عصراً',
             'note_en' => 'Sit by the water and do nothing for an hour. You are on holiday.',
             'note_ar' => 'اجلس عند الماء ولا تفعل شيئاً لساعة. أنت في عطلة.'],
            ['place' => 'msheireb-downtown', 'day' => 1, 'time_en' => '6.30pm', 'time_ar' => '٦:٣٠ مساءً',
             'note_en' => 'Dinner here — it is the easiest place in the city to eat well without a booking.',
             'note_ar' => 'العشاء هنا — أسهل مكان في المدينة للأكل الجيد بلا حجز.'],
            ['place' => 'al-zubarah-fort', 'day' => 2, 'time_en' => '9am', 'time_ar' => '٩ صباحاً',
             'note_en' => 'Day two is the north, and this is an hour and a half from Doha — leave at seven.',
             'note_ar' => 'اليوم الثاني هو الشمال، وهذه على ساعة ونصف من الدوحة — اخرج في السابعة.'],
            ['place' => 'zubarah-town-ruins', 'day' => 2, 'time_en' => '11am', 'time_ar' => '١١ صباحاً',
             'note_en' => 'No shade anywhere on this site. Go through it quickly and honestly.',
             'note_ar' => 'لا ظل في أي جزء من هذا الموقع. اجتَزه بسرعة وبصدق.'],
            ['text_en' => 'Lunch in Al Khor.', 'text_ar' => 'غداء في الخور.',
             'day' => 2, 'time_en' => '1pm', 'time_ar' => '١ ظهراً',
             'note_en' => 'The only real stop for food on this route, and the last one before the mangroves.',
             'note_ar' => 'المحطة الحقيقية الوحيدة للطعام على هذا المسار، وآخر محطة قبل المنغروف.'],
            ['place' => 'al-thakhira-mangroves', 'day' => 2, 'time_en' => '2.30pm', 'time_ar' => '٢:٣٠ عصراً',
             'note_en' => 'Better from a boat than from the bank, and the light is best in the late afternoon.',
             'note_ar' => 'أجمل من قارب لا من الضفة، والضوء أفضل في العصر.'],
            ['place' => 'al-khor-corniche', 'day' => 2, 'time_en' => '5pm', 'time_ar' => '٥ عصراً',
             'note_en' => 'Sit by the water before the drive back, or stay the night here and skip the drive entirely.',
             'note_ar' => 'اجلس عند الماء قبل طريق العودة، أو بِت هنا وتجنّب الطريق كلياً.'],
            ['place' => 'zekreet-film-city', 'day' => 3, 'time_en' => '8am', 'time_ar' => '٨ صباحاً',
             'note_en' => 'Day three is the west coast, which has almost nothing on it and is the best day of the three.',
             'note_ar' => 'اليوم الثالث هو الساحل الغربي، ولا يوجد عليه شيء تقريباً، وهو أفضل الأيام الثلاثة.'],
            ['place' => 'zekreet-limestone', 'day' => 3, 'time_en' => '10am', 'time_ar' => '١٠ صباحاً',
             'note_en' => 'The formations are a short walk from the end of the road.',
             'note_ar' => 'التشكيلات على مشية قصيرة من نهاية الطريق.'],
            ['place' => 'dukhan-escarpment', 'day' => 3, 'time_en' => '1pm', 'time_ar' => '١ ظهراً',
             'note_en' => 'Nothing to buy here. Bring lunch, water and a full tank.',
             'note_ar' => 'لا شيء يُشترى هنا. خُذ الغداء والماء وخزاناً ممتلئاً.'],
            ['place' => 'rawdat-rashed', 'day' => 3, 'time_en' => '4pm', 'time_ar' => '٤ عصراً',
             'note_en' => 'A green hollow in the middle of the stone, and the right place to end.',
             'note_ar' => 'منخفض أخضر في وسط الحجر، والمكان الصحيح للختام.'],
        ],
    ],

    [
        'slug' => 'budget-weekend',
        'title_en' => 'A free weekend', 'title_ar' => 'نهاية أسبوع مجانية',
        'intro_en' => 'Two days, two coasts, and not one ticket to book. Every stop on this route has free entry.',
        'intro_ar' => 'يومان وساحلان ولا تذكرة واحدة تحجزها. كل محطة على هذا المسار دخولها مجاني.',
        'type' => 'days', 'value' => 2, 'who' => 'budget', 'budget' => 'free',
        'stops' => [
            ['place' => 'doha-corniche', 'day' => 1, 'time_en' => '8am', 'time_ar' => '٨ صباحاً',
             'note_en' => 'Walking the corniche early is the cheapest good hour in Doha.',
             'note_ar' => 'المشي على الكورنيش باكراً أرخص ساعة جيدة في الدوحة.'],
            ['place' => 'mia-park', 'day' => 1, 'time_en' => '10am', 'time_ar' => '١٠ صباحاً',
             'note_en' => 'Free, shaded, and the skyline is the exhibit.',
             'note_ar' => 'مجانية ومظللة، وأفق المدينة هو المعرض.'],
            ['place' => 'al-bidda-park', 'day' => 1, 'time_en' => '1pm', 'time_ar' => '١ ظهراً',
             'note_en' => 'Picnic rather than restaurant — this is where the day is actually saved.',
             'note_ar' => 'نزهة لا مطعم — هنا يُوفَّر اليوم فعلاً.'],
            ['place' => 'souq-waqif', 'day' => 1, 'time_en' => '5pm', 'time_ar' => '٥ عصراً',
             'note_en' => 'Free to walk, and the cheapest dinner in the city is one street back from the main lane.',
             'note_ar' => 'المشي فيه مجاني، وأرخص عشاء في المدينة على بعد شارع واحد من الشارع الرئيسي.'],
            ['place' => 'souq-al-wakra', 'day' => 2, 'time_en' => '9am', 'time_ar' => '٩ صباحاً',
             'note_en' => 'Day two goes south, and this is the first stop on the way.',
             'note_ar' => 'اليوم الثاني نحو الجنوب، وهذه أول محطة على الطريق.'],
            ['place' => 'al-wakrah-corniche', 'day' => 2, 'time_en' => '11am', 'time_ar' => '١١ صباحاً',
             'note_en' => 'The dhow harbour is free to look at and is the photograph everybody takes.',
             'note_ar' => 'مرسى الداو مجاني للنظر، وهو الصورة التي يلتقطها الجميع.'],
            ['place' => 'sealine-dunes', 'day' => 2, 'time_en' => '1pm', 'time_ar' => '١ ظهراً',
             'note_en' => 'Free if you have your own 4x4. The dune-bashing operators are not, and neither is the recovery truck.',
             'note_ar' => 'مجاني إن كانت لديك دفع رباعي. شركات التطعيس ليست مجانية، ولا شاحنة الإنقاذ.'],
        ],
    ],

];
