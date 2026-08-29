<?php

namespace QOR\App\Domain\Social\Enum;

enum FriendshipStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
}
