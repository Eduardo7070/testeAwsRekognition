<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'photos.required' => 'Selecione pelo menos uma foto.',
            'photos.max' => 'Selecione no máximo 10 fotos por envio.',
            'photos.*.image' => 'Cada arquivo deve ser uma imagem válida.',
            'photos.*.mimes' => 'Use apenas imagens JPG ou PNG.',
            'photos.*.max' => 'Cada foto deve ter no máximo 5 MB.',
            'photos.*.uploaded' => 'O PHP recusou o upload. Confira upload_max_filesize e post_max_size no php.ini.',
        ];
    }
}
