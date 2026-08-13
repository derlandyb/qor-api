<?php

namespace App\Enums;

enum Role: string
{
    case User = 'user';   // default for every account — VISITOR/FAN, PROMOTER, VENUE alike
    case Admin = 'admin'; // regular staff/moderator role
    case SuperAdmin = 'super_admin'; // superset of Admin's powers, not a sibling (admin-auth)
}
