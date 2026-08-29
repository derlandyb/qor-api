<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationPreferenceModel extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id',
        'push_enabled',
        'email_enabled',
        'silence_all',
        'trigger_nearby_reminder',
        'trigger_event_changed_cancelled',
        'trigger_friend_interest',
        'trigger_new_regional',
    ];

    protected function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'silence_all' => 'boolean',
            'trigger_nearby_reminder' => 'boolean',
            'trigger_event_changed_cancelled' => 'boolean',
            'trigger_friend_interest' => 'boolean',
            'trigger_new_regional' => 'boolean',
        ];
    }

    protected static function newFactory(): NotificationPreferenceFactory
    {
        return NotificationPreferenceFactory::new();
    }
}
