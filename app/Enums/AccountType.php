<?php

namespace App\Enums;

enum AccountType: string
{
    case Standard = 'standard';
    case Promoter = 'promoter';
    case Venue = 'venue';
}
