<?php

namespace App\Services;

use App\Enums\BannerStatus;

final readonly class ShareMeta
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $imageUrl,
        public ?BannerStatus $bannerStatus,
    ) {}
}
