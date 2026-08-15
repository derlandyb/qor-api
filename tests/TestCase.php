<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Faked by default so any Event/EventRevision with a cover_image_path (including
        // factory-default rows a test doesn't otherwise care about) never makes a real AWS S3
        // call just to resolve a public URL. Tests asserting actual upload/URL behavior can
        // still call Storage::fake('s3') themselves — re-faking is a no-op, not a conflict.
        Storage::fake('s3');
    }
}
