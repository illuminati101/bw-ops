<?php

/*
|--------------------------------------------------------------------------
| Black & White Ops configuration
|--------------------------------------------------------------------------
| Integrations come from .env. Business settings (prices, policies, feature
| switches, company details) live in the database and are edited in
| Admin > Settings. The defaults below seed a fresh install.
*/

return [

    // Use APP_URL for every generated link (set by the Windows start script when a tunnel is running).
    'force_app_url' => (bool) env('BW_FORCE_APP_URL', false),

    // Two-factor sign-in for the admin panel: required in production unless explicitly set.
    'admin_require_mfa' => env('ADMIN_REQUIRE_MFA'),

    'integrations' => [
        'sms_driver' => env('BW_SMS_DRIVER', 'log'), // log | twilio
        'twilio' => [
            'sid' => env('TWILIO_ACCOUNT_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM_NUMBER'),
        ],
        'paystack' => [
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
        ],
        'google_maps_key' => env('GOOGLE_MAPS_API_KEY'),
        'expo_access_token' => env('EXPO_ACCESS_TOKEN'),
        'public_ws' => [
            // Where browsers and apps reach Reverb. Empty host/scheme = same as the page being viewed.
            'host' => env('BW_PUBLIC_WS_HOST') ?: null,
            'port' => env('BW_PUBLIC_WS_PORT') ?: env('REVERB_PORT', 8080),
            'scheme' => env('BW_PUBLIC_WS_SCHEME') ?: null,
            'key' => env('REVERB_APP_KEY'),
        ],
        // URL schemes the mobile apps register, so the server may redirect back into them after card setup.
        'app_schemes' => ['bwops', 'bwopscrew', 'exp', 'exps'],
        // Map tiles for the website (dark-filtered in CSS). Swap for a commercial tile provider at scale.
        'map_tiles' => env('BW_MAP_TILES', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    ],

    /*
    | Settings: key => [default, type, group, label, help]
    | type: string | text | bool | int | money | percent | json
    */
    'settings' => [
        // Company details (also used in legal pages: {{company.xxx}})
        'company.trading_name' => ['Black & White Ops', 'string', 'Company', 'Trading name'],
        'company.legal_name' => ['[Registered company name] (Pty) Ltd', 'string', 'Company', 'Registered company name'],
        'company.registration_number' => ['[CIPC registration number]', 'string', 'Company', 'Company registration number'],
        'company.vat_number' => ['[VAT number, or "Not VAT registered"]', 'string', 'Company', 'VAT number'],
        'company.vat_registered' => [false, 'bool', 'Company', 'VAT registered', 'When on, receipts and invoices show VAT separately.'],
        'company.psira_number' => ['[PSIRA registration number]', 'string', 'Company', 'PSIRA registration number'],
        'company.physical_address' => ['[Street address], Cape Town, Western Cape, [postal code]', 'text', 'Company', 'Physical address'],
        'company.service_address' => ['[Address for legal service of documents]', 'text', 'Company', 'Address for legal service'],
        'company.phone' => ['+27 21 000 0000', 'string', 'Company', 'Main phone number'],
        'company.control_room_phone' => ['+27 21 000 0000', 'string', 'Company', '24/7 control room number'],
        'company.email' => ['info@blackandwhiteops.com', 'string', 'Company', 'General email'],
        'company.bookings_email' => ['bookings@blackandwhiteops.com', 'string', 'Company', 'Bookings email'],
        'company.careers_email' => ['careers@blackandwhiteops.com', 'string', 'Company', 'Careers email'],
        'company.website' => ['https://blackandwhiteops.com', 'string', 'Company', 'Website address'],
        'company.bank_details' => ["[Bank name]\nAccount name: [Account name]\nAccount number: [Account number]\nBranch code: [Branch code]", 'text', 'Company', 'Bank details for EFT invoices'],
        'company.directors' => ['[Director names]', 'string', 'Company', 'Directors (office bearers)'],
        'company.information_officer_name' => ['[Information Officer name]', 'string', 'Company', 'POPIA Information Officer'],
        'company.information_officer_email' => ['privacy@blackandwhiteops.com', 'string', 'Company', 'Information Officer email'],
        'legal.effective_date' => ['1 November 2026', 'string', 'Company', 'Legal documents effective date'],
        'legal.terms_version' => ['2026-11-01', 'string', 'Company', 'Terms version (clients re-accept when this changes)'],

        // Feature switches
        'features.on_demand' => [true, 'bool', 'Features', 'On-demand hailing'],
        'features.advance' => [true, 'bool', 'Features', 'Advance bookings'],
        'features.team_bookings' => [true, 'bool', 'Features', 'Team bookings (several operators / vehicles)'],
        'features.multi_day' => [true, 'bool', 'Features', 'Multi-day details'],
        'features.recurring' => [true, 'bool', 'Features', 'Recurring bookings'],
        'features.multi_stop' => [true, 'bool', 'Features', 'Multi-stop trips'],
        'features.preferred_operator' => [true, 'bool', 'Features', 'Clients may request a previous operator'],
        'features.company_accounts' => [true, 'bool', 'Features', 'Company accounts'],
        'features.id_verification' => [true, 'bool', 'Features', 'ID + selfie required before first booking'],
        'features.trip_sharing' => [true, 'bool', 'Features', 'Share trip with contacts'],
        'features.arrival_pin' => [true, 'bool', 'Features', 'Arrival PIN check'],
        'features.sos' => [true, 'bool', 'Features', 'Silent SOS'],
        'features.chat' => [true, 'bool', 'Features', 'In-app chat'],
        'features.masked_calls' => [true, 'bool', 'Features', 'Masked calls (needs Twilio)'],
        'features.ratings' => [true, 'bool', 'Features', 'Post-job ratings'],
        'features.card_machine' => [true, 'bool', 'Features', 'Pay by card machine on completion'],
        'features.eft_invoicing' => [true, 'bool', 'Features', 'EFT invoicing for approved clients'],
        'features.no_show_fee' => [true, 'bool', 'Features', 'No-show and late-cancellation fees'],
        'features.reconfirmation' => [true, 'bool', 'Features', 'Clients must reconfirm advance bookings'],
        'features.strikes' => [true, 'bool', 'Features', 'Strikes and blocklist'],
        'features.card_verification' => [true, 'bool', 'Features', 'Verify card at booking'],
        'features.card_holds' => [true, 'bool', 'Features', 'Hold no-show fee on large bookings'],

        // Booking rules
        'booking.notice_minutes' => [120, 'int', 'Booking rules', 'Minimum notice for standard advance bookings (minutes)'],
        'booking.notice_minutes_team' => [2880, 'int', 'Booking rules', 'Minimum notice for team / multi-day bookings (minutes)'],
        'booking.max_days_ahead' => [180, 'int', 'Booking rules', 'How far ahead clients can book (days)'],
        'booking.reconfirm_hours' => [3, 'int', 'Booking rules', 'Reconfirmation deadline (hours before start)'],
        'booking.trusted_after' => [2, 'int', 'Booking rules', 'Completed bookings before a client becomes "trusted"', 'Trusted clients\' simple bookings confirm instantly.'],

        // Dispatch
        'dispatch.offer_timeout_seconds' => [30, 'int', 'Dispatch', 'Seconds an operator has to accept an on-demand job'],
        'dispatch.max_radius_km' => [40, 'int', 'Dispatch', 'Maximum distance to offer on-demand jobs (km)'],
        'dispatch.max_offers' => [8, 'int', 'Dispatch', 'Operators to try before handing to the control room'],
        'dispatch.location_stale_minutes' => [10, 'int', 'Dispatch', 'Ignore operators whose location is older than (minutes)'],
        'dispatch.average_speed_kmh' => [35, 'int', 'Dispatch', 'Average city speed for ETA estimates (km/h)'],

        // Cancellations & no-shows
        'cancellation.tiers' => [[['hours' => 24, 'percent' => 0], ['hours' => 2, 'percent' => 25], ['hours' => 0, 'percent' => 50]], 'json', 'Cancellations', 'Advance cancellation tiers', 'Notice (hours before start) and fee (% of quote). Ordered from most notice to least.'],
        'cancellation.on_demand_free_minutes' => [2, 'int', 'Cancellations', 'On-demand: free cancellation window (minutes)'],
        'cancellation.no_show_percent' => [50, 'percent', 'Cancellations', 'No-show fee (% of quote)'],
        'cancellation.no_show_minimum' => [500, 'money', 'Cancellations', 'Minimum no-show fee (R)'],
        'cancellation.no_show_wait_minutes' => [15, 'int', 'Cancellations', 'Operator waits this long before marking a no-show (minutes)'],

        // Strikes
        'strikes.approval_threshold' => [2, 'int', 'Strikes', 'Strikes before every booking needs approval'],
        'strikes.block_threshold' => [4, 'int', 'Strikes', 'Strikes before the account is suspended'],

        // Payments
        'payments.verify_amount' => [2.00, 'money', 'Payments', 'Card verification amount (R, refunded)'],
        'payments.hold_threshold' => [5000, 'money', 'Payments', 'Hold no-show fee on bookings over (R)'],
        'payments.vat_rate' => [15, 'percent', 'Payments', 'VAT rate (%)'],
        'payments.prices_include_vat' => [true, 'bool', 'Payments', 'Rate cards include VAT'],

        // Escalation
        'escalation.sos_ack_seconds' => [60, 'int', 'Escalation', 'Escalate an unacknowledged SOS after (seconds)'],
        'escalation.unassigned_minutes' => [5, 'int', 'Escalation', 'Escalate an on-demand job with no operator after (minutes)'],
        'escalation.advance_unassigned_hours' => [12, 'int', 'Escalation', 'Alert when a confirmed booking has no operator this close to start (hours)'],

        // Apps
        'apps.min_version_client' => ['1.0.0', 'string', 'Apps', 'Minimum client app version (older versions must update)'],
        'apps.min_version_crew' => ['1.0.0', 'string', 'Apps', 'Minimum Crew app version'],
    ],
];
