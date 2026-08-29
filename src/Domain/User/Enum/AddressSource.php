<?php

namespace QOR\App\Domain\User\Enum;

enum AddressSource: string
{
    case Manual = 'manual';
    case DeviceLocation = 'device_location';
}
