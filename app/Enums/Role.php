<?php

namespace App\Enums;

enum Role: string
{
    case User = 'user';   // default for every account — VISITOR/FAN, PROMOTER, VENUE alike
    case Admin = 'admin'; // flat admin role, no tiers (context.md, PRD §29 [OPEN])
}
