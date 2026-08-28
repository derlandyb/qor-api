<?php

namespace QOR\App\Domain\Shared;

final class UploadableFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly ?int $widthPx = null,
        public readonly ?int $heightPx = null,
    ) {
    }
}
