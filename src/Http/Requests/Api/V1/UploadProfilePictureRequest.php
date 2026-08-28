<?php

namespace QOR\App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadProfilePictureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Server-side MIME/size/dimension validation happens in FileUploadPort
     * (ARCHITECTURE.md §10) — this only enforces "it must actually be an
     * uploaded file", never a pasted URL field.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'picture' => ['required', 'file', 'image'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'picture.required' => 'A imagem é obrigatória.',
            'picture.file' => 'Envie um arquivo de imagem.',
            'picture.image' => 'Envie um arquivo de imagem.',
        ];
    }
}
