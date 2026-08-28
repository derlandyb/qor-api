<?php

namespace QOR\App\Domain\Shared\Exception;

use DomainException;

/**
 * $field names the form field the error should attach to (per T21's
 * field-specific pt-BR validation errors).
 */
class FileUploadRejected extends DomainException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
