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
            'recruiting_status' => ['required', 'string', Rule::in(config('group_discovery.recruiting_statuses', []))],
            'primary_focuses' => ['required', 'array', 'min:1'],
            'primary_focuses.*' => ['required', 'string', Rule::in(config('group_discovery.primary_focuses', []))],
            'experience_expectation' => ['required', 'string', Rule::in(config('group_discovery.experience_expectations', []))],
            'voice_expectation' => ['required', 'string', Rule::in(config('group_discovery.voice_expectations', []))],
            'preferred_languages' => ['required', 'array', 'min:1'],
            'preferred_languages.*' => ['required', 'string', Rule::in(config('group_discovery.preferred_languages', []))],
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
