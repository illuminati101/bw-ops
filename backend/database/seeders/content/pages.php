<?php

// Website pages, built from blocks that staff can edit, reorder and publish in Admin > Pages.

return [
    [
        'slug' => 'home',
        'title' => 'Armed chauffeurs & close protection in Cape Town',
        'meta_description' => 'Black & White Ops provides armed chauffeurs, close protection officers and protected transfers across Cape Town. Book ahead or hail on demand, 24/7.',
        'blocks' => [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Private security and chauffeurs in Cape Town',
                'heading' => 'Discretion, delivered.',
                'subheading' => 'Armed chauffeurs and close protection officers for people who leave nothing to chance. Book ahead, or hail on demand, day or night.',
                'primary_label' => 'Book now', 'primary_url' => '/book',
                'secondary_label' => 'Explore services', 'secondary_url' => '/services',
                'image' => 'images/hero-night-drive.jpg',
            ]],
            ['type' => 'pillars', 'data' => [
                'eyebrow' => 'Why Black & White',
                'heading' => 'Quietly formidable.',
                'items' => [
                    ['icon' => 'badge-check', 'title' => 'Vetted, every one', 'body' => 'Every operator is PSIRA-registered, background-checked and re-checked before their documents lapse. If a certificate expires, they come off the roster automatically.'],
                    ['icon' => 'steering-wheel', 'title' => 'Trained beyond the wheel', 'body' => 'Defensive and evasive driving, threat awareness and first aid, delivered with the manners of a five-star concierge.'],
                    ['icon' => 'clock', 'title' => 'On demand or by appointment', 'body' => 'Hail a protected ride in minutes, or schedule a detail days ahead. One account covers both.'],
                    ['icon' => 'eye-off', 'title' => 'Private by design', 'body' => 'Your operator sees only what the job needs, and only while it lasts. Your number is never shared, and no history stays on their phone.'],
                ],
            ]],
            ['type' => 'services', 'data' => [
                'eyebrow' => 'Services',
                'heading' => 'The right level of cover for every moment.',
                'intro' => 'From a discreet airport pickup to a full protection detail, every service is planned, monitored and priced up front.',
            ]],
            ['type' => 'steps', 'data' => [
                'eyebrow' => 'How it works',
                'heading' => 'Four steps. No surprises.',
                'items' => [
                    ['title' => 'Tell us what you need', 'body' => 'Choose a service, a time and a pickup point. Add requirements such as an armed operator or an armoured vehicle.'],
                    ['title' => 'See a firm price', 'body' => 'You see a clear quote before you confirm. Nothing is charged up front: we only verify your card.'],
                    ['title' => 'Meet your operator', 'body' => 'See who is coming, their vehicle and their live location. They confirm your private PIN before you step in.'],
                    ['title' => 'Settle afterwards', 'body' => 'When the job ends, your saved card is charged the agreed amount and your receipt arrives by email.'],
                ],
            ]],
            ['type' => 'split', 'data' => [
                'eyebrow' => 'Safety',
                'heading' => 'Safety that doesn\'t announce itself.',
                'body' => "Every booking is watched by our 24/7 control room. You can follow your operator on the map, share your trip with someone you trust, and raise a silent SOS with a single press.\n\nIf an alert isn't acknowledged within a minute, it escalates to our on-call team automatically, by SMS and then by phone.",
                'bullets' => ['Live operator tracking', 'Private arrival PIN', 'Share your trip', 'Silent SOS to our control room'],
                'visual' => 'phone',
                'cta_label' => 'About our standards', 'cta_url' => '/about',
                'reverse' => false,
            ]],
            ['type' => 'cta', 'data' => [
                'heading' => 'Cape Town, covered.',
                'body' => 'From the Atlantic Seaboard to the Winelands, from CPT arrivals to late nights in the City Bowl.',
                'primary_label' => 'Create your account', 'primary_url' => '/account/register',
                'secondary_label' => 'Talk to us', 'secondary_url' => '/contact',
            ]],
        ],
    ],
    [
        'slug' => 'about',
        'title' => 'About us',
        'meta_description' => 'Black & White Ops is a Cape Town private security and chauffeur service combining luxury chauffeuring with professional close protection.',
        'blocks' => [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'About us',
                'heading' => 'Built on a simple promise.',
                'subheading' => 'That you never have to think about your safety, because we already have.',
                'image' => 'images/about-skyline.jpg', 'compact' => true,
            ]],
            ['type' => 'text', 'data' => [
                'eyebrow' => 'Who we are',
                'heading' => 'Luxury chauffeur. Protection team. One discreet service.',
                'body' => "Black & White Ops is a Cape Town private security and chauffeur service for executives, public figures, visiting dignitaries, families and productions who need more than a ride.\n\nWe combine the polish of a luxury chauffeur with the vigilance of a close protection team, and we keep both firmly out of the spotlight.\n\nOur name says it plainly: no grey areas. Clear prices, clear rules of engagement, clear lines of communication, and operators held to the highest standard.",
            ]],
            ['type' => 'pillars', 'data' => [
                'eyebrow' => 'What we stand for',
                'heading' => 'Four standards, every job.',
                'items' => [
                    ['icon' => 'lock', 'title' => 'Discretion', 'body' => 'What happens on a job stays on the job. Need-to-know access, no gossip, no social media.'],
                    ['icon' => 'target', 'title' => 'Readiness', 'body' => 'Planned routes, briefed operators and vehicles checked before every shift.'],
                    ['icon' => 'scale', 'title' => 'Integrity', 'body' => 'Licensed, compliant and accountable, with every action logged and reviewable.'],
                    ['icon' => 'sparkles', 'title' => 'Courtesy', 'body' => 'Firm when it matters, gracious always. Our operators are an extension of your hospitality.'],
                ],
            ]],
            ['type' => 'split', 'data' => [
                'eyebrow' => 'How we keep you safe',
                'heading' => 'Compliance isn\'t a box we tick once.',
                'body' => "Every operator's PSIRA registration, firearm competency, driving permits and training certificates live in our system with their expiry dates. We are alerted 30 days before anything lapses, and if a document does expire, the system removes that operator from the duties it covers automatically.\n\nOur vehicles are tracked the same way: licence, roadworthiness and insurance, checked continuously.",
                'bullets' => ['Continuous document checks', '24/7 control room with automatic escalation', 'Audit log of every staff action', 'Client data held in South Africa under POPIA'],
                'image' => 'images/operator-briefing.jpg',
                'reverse' => true,
            ]],
            ['type' => 'cta', 'data' => [
                'heading' => 'Ready when you are.',
                'body' => 'Create an account in minutes and make your first booking today.',
                'primary_label' => 'Get started', 'primary_url' => '/account/register',
                'secondary_label' => 'Contact us', 'secondary_url' => '/contact',
            ]],
        ],
    ],
    [
        'slug' => 'services',
        'title' => 'Services',
        'meta_description' => 'Secure chauffeurs, close protection, protected transfers, airport meet & protect, event protection and extended details in Cape Town.',
        'blocks' => [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Services',
                'heading' => 'Choose your level of cover.',
                'subheading' => 'Every service is delivered by vetted, PSIRA-registered operators and monitored by our control room from start to finish.',
                'image' => 'images/services-sedan.jpg', 'compact' => true,
            ]],
            ['type' => 'services', 'data' => ['heading' => '', 'intro' => '', 'detailed' => true]],
            ['type' => 'cta', 'data' => [
                'heading' => 'Not sure which service you need?',
                'body' => 'Tell us about the occasion and we will recommend the right cover, with a firm quote.',
                'primary_label' => 'Ask our team', 'primary_url' => '/contact',
            ]],
        ],
    ],
    [
        'slug' => 'faq',
        'title' => 'Frequently asked questions',
        'meta_description' => 'Answers about booking, safety, privacy, payment and our operators.',
        'blocks' => [
            ['type' => 'hero', 'data' => ['eyebrow' => 'FAQ', 'heading' => 'Questions, answered.', 'subheading' => 'Can\'t find what you need? Our team is a message away.', 'compact' => true]],
            ['type' => 'faq', 'data' => ['heading' => '']],
            ['type' => 'cta', 'data' => ['heading' => 'Still have a question?', 'body' => 'Call us or send a message and a person will reply.', 'primary_label' => 'Contact us', 'primary_url' => '/contact']],
        ],
    ],
    [
        'slug' => 'careers',
        'title' => 'Careers',
        'meta_description' => 'Join Black & White Ops as a chauffeur or close protection officer in Cape Town.',
        'blocks' => [
            ['type' => 'hero', 'data' => ['eyebrow' => 'Careers', 'heading' => 'Join the detail.', 'subheading' => 'We are always looking for exceptional chauffeurs and close protection officers in Cape Town.', 'compact' => true, 'image' => 'images/careers.jpg']],
            ['type' => 'split', 'data' => [
                'eyebrow' => 'What we look for',
                'heading' => 'Calm under pressure. Impeccable in person.',
                'body' => "You take pride in your appearance, your preparation and your discretion. You know that the best protection work is the work nobody notices.\n\nWe offer employed and contract positions, with transparent pay for every job and a Crew app that shows your schedule, earnings and documents in one place.",
                'bullets' => ['Valid PSIRA registration', 'Clean criminal record', 'Driver\'s licence and PrDP (for chauffeurs)', 'Firearm competency (for armed roles)', 'First aid certificate (advantageous)'],
                'reverse' => false,
            ]],
            ['type' => 'form', 'data' => ['form' => 'careers', 'heading' => 'Apply now', 'intro' => 'Tell us about yourself and upload your CV and certificates. We review every application and will contact you if there is a fit.']],
        ],
    ],
    [
        'slug' => 'corporate',
        'title' => 'Corporate services',
        'meta_description' => 'Company accounts for executive protection, event security and VIP transport in Cape Town, with monthly invoicing.',
        'blocks' => [
            ['type' => 'hero', 'data' => ['eyebrow' => 'Corporate', 'heading' => 'Security your whole team can book.', 'subheading' => 'Company accounts for executive travel, visiting VIPs, events and productions, with monthly invoicing for approved companies.', 'compact' => true, 'image' => 'images/corporate.jpg']],
            ['type' => 'pillars', 'data' => [
                'eyebrow' => 'Company accounts',
                'heading' => 'Built for PAs, travel desks and event teams.',
                'items' => [
                    ['icon' => 'users', 'title' => 'Multiple bookers', 'body' => 'Authorise PAs, travel coordinators and event managers to book on the company account.'],
                    ['icon' => 'file-text', 'title' => 'Monthly invoicing', 'body' => 'Approved companies settle by EFT against a single monthly invoice.'],
                    ['icon' => 'user-check', 'title' => 'Book for your principals', 'body' => 'Add the executive or guest you are booking for. They get their operator details and tracking link by SMS.'],
                    ['icon' => 'bar-chart', 'title' => 'One clear history', 'body' => 'Every booking, receipt and invoice in one place, ready for your finance team.'],
                ],
            ]],
            ['type' => 'form', 'data' => ['form' => 'corporate', 'heading' => 'Open a company account', 'intro' => 'Tell us about your organisation and what you need. We will set up your account and call you to discuss your requirements.']],
        ],
    ],
    [
        'slug' => 'contact',
        'title' => 'Contact us',
        'meta_description' => 'Contact Black & White Ops in Cape Town. 24/7 control room for bookings and emergencies.',
        'blocks' => [
            ['type' => 'hero', 'data' => ['eyebrow' => 'Contact', 'heading' => 'Talk to a person.', 'subheading' => 'Our control room is staffed around the clock. For bookings, the app and website are quickest.', 'compact' => true]],
            ['type' => 'contact', 'data' => ['heading' => 'Get in touch']],
        ],
    ],
];
