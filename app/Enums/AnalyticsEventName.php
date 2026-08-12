<?php

namespace App\Enums;

enum AnalyticsEventName: string
{
    case EventViewed = 'event_viewed';
    case EventShared = 'event_shared';
    case EventFavorited = 'event_favorited';
    case EventUnfavorited = 'event_unfavorited';
    case SearchPerformed = 'search_performed';
    case FilterApplied = 'filter_applied';
    case MapOpened = 'map_opened';
    case MapEventSelected = 'map_event_selected';
    case TicketLinkClicked = 'ticket_link_clicked';
    case PromoterInstagramClicked = 'promoter_instagram_clicked';
    case PromoterWhatsappClicked = 'promoter_whatsapp_clicked';
    case VenueContactClicked = 'venue_contact_clicked';

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
