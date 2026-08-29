<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use QOR\App\Domain\Notification\Enum\NotificationChannel;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;

/**
 * @property-read NotificationTriggerType $trigger_type
 * @property-read NotificationChannel $channel
 * @property-read \Illuminate\Support\Carbon $sent_at
 */
class NotificationLogModel extends Model
{
    protected $table = 'notification_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'trigger_type',
        'event_id',
        'channel',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => NotificationTriggerType::class,
            'channel' => NotificationChannel::class,
            'sent_at' => 'datetime',
        ];
    }
}
