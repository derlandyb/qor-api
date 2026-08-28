<?php

namespace QOR\App\Domain\User\Enum;

enum ConsentableType: string
{
    case User = 'user';
    case AdminUser = 'admin_user';
}
