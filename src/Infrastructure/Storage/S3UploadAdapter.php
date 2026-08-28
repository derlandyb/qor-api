<?php

namespace QOR\App\Infrastructure\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use QOR\App\Domain\Shared\Exception\FileUploadRejected;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Shared\UploadableFile;

class S3UploadAdapter implements FileUploadPort
{
    public function upload(UploadableFile $file, string $directory): string
    {
        $this->validate($file);

        $key = trim($directory, '/').'/'.Str::uuid().'.'.$this->extensionFor($file->mimeType);

        $contents = file_get_contents($file->path);

        if ($contents === false) {
            throw new FileUploadRejected('file', 'Não foi possível ler o arquivo enviado.');
        }

        Storage::disk('s3')->put($key, $contents, 'public');

        return Storage::disk('s3')->url($key);
    }

    private function validate(UploadableFile $file): void
    {
        /** @var array{allowed_mime_types: list<string>, max_size_kb: int, min_width_px: int, min_height_px: int, max_width_px: int, max_height_px: int} $rules */
        $rules = config('qor.uploads.image');

        if (! in_array($file->mimeType, $rules['allowed_mime_types'], true)) {
            throw new FileUploadRejected('file', "Tipo de arquivo não permitido: {$file->mimeType}.");
        }

        if ($file->sizeBytes / 1024 > $rules['max_size_kb']) {
            throw new FileUploadRejected('file', 'O arquivo excede o tamanho máximo permitido.');
        }

        [$width, $height] = $this->dimensions($file);

        if ($width < $rules['min_width_px'] || $height < $rules['min_height_px']) {
            throw new FileUploadRejected('file', 'A imagem é menor que as dimensões mínimas permitidas.');
        }

        if ($width > $rules['max_width_px'] || $height > $rules['max_height_px']) {
            throw new FileUploadRejected('file', 'A imagem excede as dimensões máximas permitidas.');
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dimensions(UploadableFile $file): array
    {
        if ($file->widthPx !== null && $file->heightPx !== null) {
            return [$file->widthPx, $file->heightPx];
        }

        $size = @getimagesize($file->path);

        if ($size === false) {
            throw new FileUploadRejected('file', 'Não foi possível ler as dimensões da imagem.');
        }

        return [$size[0], $size[1]];
    }

    private function extensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }
}
