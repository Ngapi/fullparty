<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends GroupDetailsRequest
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            ...$this->baseRules(),
            'group_type' => ['required', 'string', Rule::in(Group::TYPES)],
            'slug' => [
                'required',
                'string',
                'max:8',
                'regex:/^[a-z]{1,8}$/',
                Rule::notIn(['admin', 'api', 'auth', 'groups', 'group', 'invite', 'invites', 'login', 'register', 'settings']),
                Rule::unique('groups', 'slug'),
            ],
        ];
    }
}
