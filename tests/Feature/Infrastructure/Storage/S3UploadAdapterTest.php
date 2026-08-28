<?php

namespace Tests\Feature\Infrastructure\Storage;

use Illuminate\Support\Facades\Storage;
use QOR\App\Domain\Shared\Exception\FileUploadRejected;
use QOR\App\Domain\Shared\UploadableFile;
use QOR\App\Infrastructure\Storage\S3UploadAdapter;
use Tests\TestCase;

class S3UploadAdapterTest extends TestCase
{
    private function fixturePath(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $path = tempnam(sys_get_temp_dir(), 'qor_upload_test_').'.jpg';
        imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }

    public function test_GIVEN_a_valid_image_WHEN_uploading_THEN_it_round_trips_to_a_retrievable_url(): void
    {
        $path = $this->fixturePath(800, 600);

        $file = new UploadableFile(
            path: $path,
            originalName: 'cover.jpg',
            mimeType: 'image/jpeg',
            sizeBytes: filesize($path),
        );

        $adapter = new S3UploadAdapter();

        $url = $adapter->upload($file, 'events/covers');

        $this->assertStringContainsString('events/covers/', $url);

        $storedFiles = Storage::disk('s3')->files('events/covers');
        $this->assertNotEmpty($storedFiles);
        $this->assertNotEmpty(Storage::disk('s3')->get($storedFiles[0]));

        @unlink($path);
    }

    public function test_GIVEN_a_disallowed_mime_type_WHEN_uploading_THEN_it_is_rejected_before_any_s3_call(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'qor_upload_test_').'.pdf';
        file_put_contents($path, '%PDF-1.4 not really a pdf');

        $file = new UploadableFile(
            path: $path,
            originalName: 'doc.pdf',
            mimeType: 'application/pdf',
            sizeBytes: filesize($path),
        );

        $adapter = new S3UploadAdapter();

        $this->expectException(FileUploadRejected::class);

        try {
            $adapter->upload($file, 'events/covers');
        } finally {
            @unlink($path);
        }
    }

    public function test_GIVEN_an_image_smaller_than_the_minimum_dimensions_WHEN_uploading_THEN_it_is_rejected(): void
    {
        $path = $this->fixturePath(50, 50);

        $file = new UploadableFile(
            path: $path,
            originalName: 'tiny.jpg',
            mimeType: 'image/jpeg',
            sizeBytes: filesize($path),
        );

        $adapter = new S3UploadAdapter();

        $this->expectException(FileUploadRejected::class);

        try {
            $adapter->upload($file, 'events/covers');
        } finally {
            @unlink($path);
        }
    }

    public function test_GIVEN_an_image_larger_than_the_maximum_dimensions_WHEN_uploading_THEN_it_is_rejected(): void
    {
        config(['qor.uploads.image.max_width_px' => 100, 'qor.uploads.image.max_height_px' => 100]);

        $path = $this->fixturePath(800, 600);

        $file = new UploadableFile(
            path: $path,
            originalName: 'huge.jpg',
            mimeType: 'image/jpeg',
            sizeBytes: filesize($path),
        );

        $adapter = new S3UploadAdapter();

        $this->expectException(FileUploadRejected::class);

        try {
            $adapter->upload($file, 'events/covers');
        } finally {
            @unlink($path);
        }
    }

    public function test_GIVEN_a_file_larger_than_the_maximum_size_WHEN_uploading_THEN_it_is_rejected(): void
    {
        config(['qor.uploads.image.max_size_kb' => 1]);

        $path = $this->fixturePath(800, 600);

        $file = new UploadableFile(
            path: $path,
            originalName: 'oversized.jpg',
            mimeType: 'image/jpeg',
            sizeBytes: filesize($path),
        );

        $adapter = new S3UploadAdapter();

        $this->expectException(FileUploadRejected::class);

        try {
            $adapter->upload($file, 'events/covers');
        } finally {
            @unlink($path);
        }
    }

    public function test_GIVEN_dimensions_supplied_directly_on_the_file_WHEN_uploading_THEN_getimagesize_is_not_used(): void
    {
        $path = $this->fixturePath(800, 600);

        $file = new UploadableFile(
            path: $path,
            originalName: 'cover.png',
            mimeType: 'image/png',
            sizeBytes: filesize($path),
            widthPx: 800,
            heightPx: 600,
        );

        $adapter = new S3UploadAdapter();

        $url = $adapter->upload($file, 'events/covers');

        $this->assertStringContainsString('.png', $url);

        @unlink($path);
    }

    public function test_GIVEN_a_webp_image_WHEN_uploading_THEN_the_extension_is_mapped_correctly(): void
    {
        $path = $this->fixturePath(800, 600);

        $file = new UploadableFile(
            path: $path,
            originalName: 'cover.webp',
            mimeType: 'image/webp',
            sizeBytes: filesize($path),
            widthPx: 800,
            heightPx: 600,
        );

        $adapter = new S3UploadAdapter();

        $url = $adapter->upload($file, 'events/covers');

        $this->assertStringContainsString('.webp', $url);

        @unlink($path);
    }

    public function test_GIVEN_a_file_whose_dimensions_cannot_be_read_WHEN_uploading_THEN_it_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'qor_upload_test_').'.jpg';
        file_put_contents($path, str_repeat('a', 1024));

        $file = new UploadableFile(
            path: $path,
            originalName: 'notarealimage.jpg',
            mimeType: 'image/jpeg',
            sizeBytes: filesize($path),
        );

        $adapter = new S3UploadAdapter();

        $this->expectException(FileUploadRejected::class);

        try {
            $adapter->upload($file, 'events/covers');
        } finally {
            @unlink($path);
        }
    }
}
