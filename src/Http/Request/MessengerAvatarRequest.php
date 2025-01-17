<?php

namespace RTippin\Messenger\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use RTippin\Messenger\Facades\Messenger;

class MessengerAvatarRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $limit = Messenger::getProviderAvatarSizeLimit();
        $mimes = Messenger::getProviderAvatarMimeTypes();

        return [
            'image' => "required|max:{$limit}|file|mimes:{$mimes}",
        ];
    }
}
