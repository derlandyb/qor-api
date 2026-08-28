<?php

namespace QOR\App\Domain\User\Enum;

enum ConsentType: string
{
    case Terms = 'terms';
    case Location = 'location';
}
