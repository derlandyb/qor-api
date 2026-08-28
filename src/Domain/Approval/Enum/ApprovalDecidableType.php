<?php

namespace QOR\App\Domain\Approval\Enum;

enum ApprovalDecidableType: string
{
    case Venue = 'venue';
    case Promoter = 'promoter';
    case Event = 'event';
}
