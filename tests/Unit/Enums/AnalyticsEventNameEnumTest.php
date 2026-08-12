<?php

use App\Enums\AnalyticsEventName;

it('given the analytics event allowlist when values are listed then exactly the 12 confirmed events are present', function () {
    expect(AnalyticsEventName::values())->toEqualCanonicalizing([
        'event_viewed',
        'event_shared',
        'event_favorited',
        'event_unfavorited',
        'search_performed',
        'filter_applied',
        'map_opened',
        'map_event_selected',
        'ticket_link_clicked',
        'promoter_instagram_clicked',
        'promoter_whatsapp_clicked',
        'venue_contact_clicked',
    ]);
});
