<?php
/**
 * English UI strings. Keys are grouped with a dot prefix by area.
 * Anything missing here falls back to the key itself, so gaps are visible.
 */

return [
    // -- chrome ------------------------------------------------------------
    'site.name'            => 'Qatar Unseen',
    'site.tagline'         => 'How much of Qatar have you actually seen?',

    'nav.map'              => 'Map',
    'nav.places'           => 'Places',
    'nav.guide'            => 'Visitor guide',
    'nav.about'            => 'About',

    // -- appearance --------------------------------------------------------
    // Two states, with the operating system's own preference as the starting
    // point: a visitor who never touches the button gets what their device asks
    // for, and one who does gets what they chose. There is deliberately no third
    // "follow the system again" state for anybody to have to understand.
    //
    // Each label describes what pressing the button will do rather than what the
    // theme currently is, which is also what the icon shows.
    'theme.to_dark'        => 'Switch to the dark theme',
    'theme.to_light'       => 'Switch to the light theme',

    // -- home / hero -------------------------------------------------------
    'hero.eyebrow'         => ':places places · :muns municipalities',
    'hero.title'           => 'How much of Qatar have you actually seen?',
    'hero.sub'             => 'Most people here live inside a twenty-minute circle. Mark what you have actually been to, and find out how much is left.',
    'hero.cta'             => 'Start marking',
    'hero.secondary'       => 'Browse places first',

    // -- map ---------------------------------------------------------------
    'map.title'            => 'Your Qatar map',
    'map.hint'             => 'Tap a municipality on the map, or a place in the list.',
    'map.progress'         => ':done of :total places',
    'map.mun_progress'     => ':done of :total municipalities',
    'map.search'           => 'Search a place…',
    'map.select_all'       => 'Mark all',
    'map.clear_all'        => 'Clear all',
    'map.show_labels'      => 'Show labels',
    'map.zoom_hint'        => 'Scroll or pinch to zoom, drag to pan',
    'map.reset_view'       => 'Reset view',
    'map.no_match'         => 'Nothing matches “:q”.',
    'map.all_marked'       => 'All :total marked. Genuinely impressive.',
    'map.empty'            => 'Nothing marked yet.',
    'map.mark_all'         => 'Mark all :n here',
    'map.loaded'           => 'Loaded the shared map.',
    'map.keep_yours'       => 'You already have a map — clear it first to load this one.',
    'map.marked_one'       => ':name is marked on your map.',
    'map.marked_many'      => ':n places from your trip are marked on your map.',

    // -- progress / levels -------------------------------------------------
    'level.label'          => 'Level',
    'level.next'           => ':n more to reach :level',
    'level.max'            => 'You have reached the top level.',

    // -- badges ------------------------------------------------------------
    'badges.title'         => 'Badges',
    'badges.earned'        => 'Earned',
    'badges.locked'        => 'Locked',
    'badges.count'         => ':done of :total earned',
    'badges.streak_hint'   => 'Keep going — a few more and this one unlocks.',

    // -- personality -------------------------------------------------------
    'personality.title'    => 'Your travel personality',
    'personality.hint'     => 'Mark a few more places and we will work this out.',

    // -- share / export ----------------------------------------------------
    'share.title'          => 'Share your map',
    'share.name_label'     => 'Name on the card',
    'share.name_placeholder' => 'Leave blank for “My Qatar”',
    'share.theme'          => 'Card style',
    'share.size'           => 'Card size',
    'share.size_post'      => 'Post',
    'share.size_story'     => 'Story',
    'share.size_wide'      => 'Facebook',
    'share.photo'          => 'Add a photo',
    'share.photo_change'   => 'Change photo',
    'share.photo_remove'   => 'Remove',
    'share.photo_hint'     => 'Stays on your device unless you create a link.',
    'share.download_png'   => 'Download image',
    'share.download_pdf'   => 'Download PDF',
    'share.make_link'      => 'Create a share link',
    'share.link_ready'     => 'Your link is ready',
    'share.copy'           => 'Copy link',
    'share.copied'         => 'Copied',
    'share.creating'       => 'Creating…',
    'share.working'        => 'Drawing your card…',
    'share.error'          => 'Could not create the link. Try again in a moment.',
    'share.rate_limited'   => 'That is a lot of maps in one hour. Try again shortly.',
    'share.private_note'   => 'No account needed. Your link is public to anyone who has it.',

    // -- the exported card itself ------------------------------------------
    // The big number on the card already reads "23 / 48", so this line carries
    // the share rather than repeating the total.
    'card.pct'             => ':pct% of Qatar visited',
    'card.credit'          => 'Boundaries © unseenqatar.com',
    'card.anon_name'       => 'A Qatar resident',

    // -- places guide ------------------------------------------------------
    'places.title'         => 'Places',
    'places.sub'           => 'Everywhere worth the drive, with what it actually costs.',
    'places.all_mun'       => 'All municipalities',
    'places.all_cats'      => 'All categories',
    'places.free'          => 'Free entry',
    'places.hidden'        => 'Hidden gems only',
    'places.near_me'       => 'Near me',
    'places.filters'       => 'Filters',
    'places.clear_filters' => 'Clear filters',
    'places.results'       => ':n places',
    'places.results_one'   => '1 place',
    'places.none'          => 'Nothing matches those filters yet.',
    'places.sort_name'     => 'A–Z',
    'places.sort_mun'      => 'By municipality',
    'places.sort_fee'      => 'Cheapest first',
    'places.sort_default'  => 'Recommended',
    'places.sort_distance' => 'Nearest first',
    'places.search_ph'     => 'Search places…',
    'places.family'        => 'Good with children',
    'places.metro'         => 'Near the metro',
    'places.hidden_tag'    => 'Hidden gem',
    'places.metro_tag'     => 'Metro',
    'places.showing'       => 'Showing :n of :total',
    'places.near_done'     => 'Nearest to you first.',
    'places.distance'      => ':km km away',

    // -- place detail ------------------------------------------------------
    'place.back'           => 'All places',
    'place.best_time'      => 'Best time to go',
    'place.duration'       => 'How long',
    'place.fee'            => 'Cost',
    'place.tips'           => 'What people get wrong',
    'place.about'          => 'About',
    'place.map_link'       => 'Open in Google Maps',
    'place.official'       => 'Official site',
    'place.verified'       => 'Details checked :date',
    'place.mark'           => 'I have been here',
    'place.marked'         => 'Marked as visited',
    'place.in_mun'         => 'in :mun',
    'place.near'           => 'near :loc',
    'place.source'         => 'Source',
    'place.related'        => 'Nearby',
    'place.area'           => 'Area',
    'place.municipality'   => 'Municipality',
    'place.season'         => 'Season',
    'place.family'         => 'Good with children',
    'place.metro'          => 'Metro nearby',
    'place.indoor'         => 'Indoor',
    'place.yes'            => 'Yes',
    'place.open_map'       => 'See it on the map',
    'place.open_map_hint'  => 'Opens the map with this place marked.',

    // -- my trip (the "want to go" list) -----------------------------------
    // Kept deliberately separate from the map's marks: "I have been here" and
    // "I want to go here" are different questions, and one must never be read
    // as the other.
    'nav.trip'             => 'My trip',
    'trip.title'           => 'My trip',
    'trip.sub'             => 'What you want to see, kept apart from what you already have.',
    'trip.add'             => 'Want to go',
    'trip.added'           => 'On your trip',
    'trip.add_one'         => ':name is on your trip.',
    'trip.remove_one'      => ':name came off your trip.',
    'trip.add_aria'        => 'Add :name to my trip',
    'trip.remove_aria'     => 'Remove :name from my trip',
    'trip.empty'           => 'Nothing on your trip yet.',
    'trip.empty_hint'      => 'Press “Want to go” on any place in the guide and it lands here.',
    'trip.empty_cta'       => 'Browse the places',
    'trip.empty_plans'     => 'Start from a ready-made plan',
    'trip.total'           => 'Time at these places',
    'trip.total_none'      => 'No times recorded for these',
    'trip.unknown'         => ':n with no time recorded',
    'trip.travel_note'     => 'That is time spent at the places themselves. Getting between them is not counted.',
    'trip.copy'            => 'Copy as text',
    'trip.copied'          => 'Copied',
    'trip.copy_failed'     => 'Could not copy — select the text by hand.',
    'trip.copy_head'       => 'My Qatar trip — :n places',
    'trip.copy_total'      => 'About :time at the places themselves.',
    'trip.copy_gone'       => ':n no longer in the guide',
    'trip.clear'           => 'Clear the trip',
    'trip.clear_confirm'   => 'Take every place off your trip?',
    'trip.count'           => ':n places',
    'trip.count_one'       => '1 place',
    'trip.gone'            => 'No longer in the guide',
    'trip.gone_note'       => ':n place(s) on your trip are not in the guide any more. Remove them below.',
    'trip.remove'          => 'Remove',
    'trip.open'            => 'Open',
    'trip.on_device'       => 'Kept in this browser only — no account, nothing sent to a server. Clearing your browser data clears this.',
    'trip.needs_js'        => 'Your trip is kept in this browser, not on the server, so this page needs JavaScript to draw it. The places themselves are all in the guide.',
    'trip.loading'         => 'Reading your trip…',
    'trip.see_map'         => 'Mark these on the map',

    // -- ready-made plans --------------------------------------------------
    //স্তর ৩ item ৮: whole routes somebody can take without planning anything.
    // A plan is described by its length first, so that is the word the chips and
    // the card's figure are built from — days for a trip, hours for a layover,
    // because an eight-hour stopover described as "1 day" is wrong in a way that
    // matters to somebody with a connecting flight.
    'nav.plans'            => 'Plans',
    'plans.title'          => 'Ready-made plans',
    'plans.sub'            => 'A day, a weekend or a long layover — already worked out, with the driving kept sensible.',
    'plans.see_all'        => 'See all plans',
    'plans.length'         => 'How long',
    'plans.who'            => 'Who it is for',
    'plans.clear'          => 'Clear',
    'plans.count'          => ':n plans',
    'plans.count_one'      => '1 plan',
    'plans.showing'        => 'Showing :n of :total',
    'plans.none'           => 'No plans match that yet.',
    'plans.stops'          => ':n stops',
    'plans.stops_one'      => '1 stop',
    'plans.about_time'     => 'about :time',
    'plans.empty_h'        => 'No plans published yet',
    'plans.empty_p'        => 'The plans are written in the admin panel and read from MySQL, and there are none published at the moment.',
    'plans.empty_where'    => 'Add them under Itineraries in the panel; anything marked draft stays invisible here.',

    // The two units the card's figure is labelled with.
    'itin.unit_days'       => 'days',
    'itin.unit_hours'      => 'hours',
    'itin.day_one'         => '1 day',
    'itin.days'            => ':n days',
    'itin.hour_one'        => '1 hour',
    'itin.hours'           => ':n hours',

    // -- one plan ----------------------------------------------------------
    'plan.back'            => 'All plans',
    'plan.day'             => 'Day :n',
    'plan.route'           => 'The route',
    'plan.no_stops'        => 'This plan has no stops written yet.',
    'plan.f_length'        => 'Length',
    'plan.f_for'           => 'Best for',
    'plan.f_budget'        => 'Budget',
    'plan.f_stops'         => 'Stops',
    'plan.f_visiting'      => 'Time at the stops',
    'plan.about_time'      => 'about :time at the stops',
    'plan.unknown_time'    => ':n of these have no time recorded, so the total above is part of the day rather than all of it.',
    'plan.verified'        => 'Details checked :date',
    'plan.add_all'         => 'Add the whole plan to my trip',
    'plan.added_all'       => 'The whole plan is on your trip',
    'plan.added_n'         => ':n stops went onto your trip.',
    'plan.already'         => 'Every stop is already on your trip.',
    'plan.open_map'        => 'See the route on the map',
    'plan.open_map_hint'   => 'Marks every area this plan visits, so you can see the shape of the day before you commit to it.',

    // -- who a plan is for -------------------------------------------------
    // The same six values the itineraries table allows, so a new audience in the
    // enum shows its slug rather than a blank until it is given a word here.
    'who.any'              => 'Anyone',
    'who.family'           => 'Families',
    'who.couple'           => 'Couples',
    'who.budget'           => 'On a budget',
    'who.adventure'        => 'Adventurers',
    'who.solo'             => 'Solo travellers',

    // -- visitor guide -----------------------------------------------------
    'guide.title'          => 'Visitor guide',
    'guide.sub'            => 'The things nobody tells you before you land.',
    'guide.toc'            => 'On this page',
    'guide.note_title'     => 'Before you rely on any of this',
    'guide.note'           => 'Timings, fares and rules in Qatar change often, and this guide is a starting point rather than a timetable. Check with the venue or the operator before you set out.',
    'guide.places_cta'     => 'Browse the places guide',

    // -- locality kinds ----------------------------------------------------
    'kind.city'            => 'City',
    'kind.town'            => 'Town',
    'kind.district'        => 'District',
    'kind.landmark'        => 'Landmark',
    'kind.industrial'      => 'Industrial',
    'kind.natural'         => 'Nature',

    // -- cost levels -------------------------------------------------------
    'fee.free'             => 'Free',
    'fee.low'              => 'Under QAR 50',
    'fee.medium'           => 'QAR 50–150',
    'fee.high'             => 'Over QAR 150',

    // -- units -------------------------------------------------------------
    'unit.min'             => 'min',
    'unit.hr'              => 'h',
    'unit.km'              => 'km',
    'unit.half_hour'       => '30 min',

    // -- seasons -----------------------------------------------------------
    'season.all'           => 'Year-round',
    'season.winter'        => 'Winter only',
    'season.summer'        => 'Indoor / summer',

    // -- shared map page ---------------------------------------------------
    'sm.title'             => ':name’s Qatar map',
    'sm.title_anon'        => 'A Qatar map',
    'sm.progress'          => ':done of :total places · :pct%',
    'sm.cta'               => 'Make your own map',
    'sm.cta_sub'           => 'It takes about a minute, and you do not need an account.',
    'sm.views'             => ':n views',
    'sm.not_found'         => 'That map link does not exist, or has been removed.',
    'sm.cta_btn'           => 'Start yours',
    'sm.start_from'        => 'Start from this map',

    // -- geolocation -------------------------------------------------------
    'geo.denied'           => 'Location permission was declined.',
    'geo.unavailable'      => 'Your browser could not get a location.',
    'geo.unsupported'      => 'This browser does not support location.',
    'geo.locating'         => 'Finding you…',

    // -- footer ------------------------------------------------------------
    'footer.disclaimer'    => 'Details change. Check official sources before you travel.',
    'footer.credit'        => 'Boundaries from unseenqatar.com',
    'footer.made'          => 'Made in Qatar.',

    // -- errors ------------------------------------------------------------
    'err.404'              => 'That page does not exist.',
    'err.back'             => 'Back to the map',
];
