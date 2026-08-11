<?php

namespace App\Enums;

// Brazilian "Classificação Indicativa"; unset (null) means not set — omit on card.
enum AgeRating: string
{
    case Livre = 'L';
    case Ten = '10';
    case Twelve = '12';
    case Fourteen = '14';
    case Sixteen = '16';
    case Eighteen = '18';
}
