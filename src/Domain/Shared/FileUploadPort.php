<?php

namespace QOR\App\Domain\Shared;

use QOR\App\Domain\Shared\Exception\FileUploadRejected;

interface FileUploadPort
{
    /**
     * Validates $file (MIME/size/dimensions) before forwarding it to S3 —
     * never a pasted URL (ARCHITECTURE.md §10). Returns the stored URL.
     *
     * @throws FileUploadRejected
     */
    public function upload(UploadableFile $file, string $directory): string;
}
