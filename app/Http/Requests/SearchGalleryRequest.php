<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchGalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selfie' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'selfie.required' => 'Envie uma selfie ou capture uma foto pela câmera.',
            'selfie.image' => 'Envie uma imagem válida.',
            'selfie.mimes' => 'Use uma selfie JPG ou PNG.',
            'selfie.max' => 'A selfie deve ter no máximo 5 MB.',
            'selfie.uploaded' => 'O PHP recusou o upload da selfie. Confira os limites do php.ini.',
            'consent.accepted' => 'Autorize o processamento da sua selfie para realizar a busca.',
        ];
    }
}
