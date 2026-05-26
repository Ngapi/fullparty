<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class UpdateGroupSettingsRequest extends GroupDetailsRequest
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return Arr::only($this->baseRules(), [
            'name',
            'description',
            'profile_picture',
            'banner_image',
            'discord_invite_url',
            'datacenter',
            'is_public',
            'is_visible',
        ]);
    }
}
