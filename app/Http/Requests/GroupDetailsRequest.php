<?php

namespace App\Http\Requests;

use App\Support\Input\RequestTextInputSanitizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class GroupDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'banner_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'discord_invite_url' => ['nullable', 'url', 'max:500'],
            'datacenter' => ['required', 'string', Rule::in(config('datacenters.values', []))],
            'is_public' => ['required', 'boolean'],
            'is_visible' => ['required', 'boolean'],
            'recruiting_status' => ['nullable', 'string', Rule::in(config('group_discovery.recruiting_statuses', []))],
            'primary_focuses' => ['nullable', 'array'],
            'primary_focuses.*' => ['required', 'string', Rule::in(config('group_discovery.primary_focuses', []))],
            'experience_expectation' => ['nullable', 'string', Rule::in(config('group_discovery.experience_expectations', []))],
            'voice_expectation' => ['nullable', 'string', Rule::in(config('group_discovery.voice_expectations', []))],
            'preferred_languages' => ['nullable', 'array'],
            'preferred_languages.*' => ['required', 'string', Rule::in(config('group_discovery.preferred_languages', []))],
            'tags' => ['nullable', 'array', 'max:'.config('group_discovery.max_tags', 12)],
            'tags.*' => ['required', 'string', 'max:50'],
            'active_timezone' => ['nullable', 'timezone:all'],
            'active_days' => ['nullable', 'array'],
            'active_days.*' => ['required', 'string', Rule::in(config('group_discovery.active_days', []))],
            'active_start_time' => ['nullable', 'date_format:H:i'],
            'active_end_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'profile_picture.mimes' => __('groups.index.create_modal.validation.image_invalid_format'),
            'banner_image.mimes' => __('groups.index.create_modal.validation.image_invalid_format'),
        ];
    }

    protected function prepareForValidation(): void
    {
        app(RequestTextInputSanitizer::class)->sanitize(
            $this,
            [
                'name',
                'discord_invite_url',
                'recruiting_status',
                'primary_focuses.*',
                'experience_expectation',
                'voice_expectation',
                'preferred_languages.*',
                'tags.*',
                'active_timezone',
                'active_days.*',
                'active_start_time',
                'active_end_time',
            ],
            ['description'],
        );

        $normalized = [];

        foreach (['primary_focuses', 'preferred_languages', 'active_days'] as $field) {
            if ($this->exists($field)) {
                $normalized[$field] = $this->normalizeStringArray($this->input($field));
            }
        }

        if ($this->exists('tags')) {
            $normalized['tags'] = $this->normalizeTags($this->input('tags'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasActiveDays = $this->filledArray('active_days');
            $hasActiveStart = filled($this->input('active_start_time'));
            $hasActiveEnd = filled($this->input('active_end_time'));
            $hasTimezone = filled($this->input('active_timezone'));

            if (($hasActiveStart && ! $hasActiveEnd) || (! $hasActiveStart && $hasActiveEnd)) {
                $validator->errors()->add(
                    ! $hasActiveStart ? 'active_start_time' : 'active_end_time',
                    __('groups.common.validation.active_time_pair_required')
                );
            }

            if (($hasActiveDays || $hasActiveStart || $hasActiveEnd) && ! $hasTimezone) {
                $validator->errors()->add(
                    'active_timezone',
                    __('groups.common.validation.active_timezone_required')
                );
            }

        });
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_values(array_filter($value, fn (mixed $entry) => is_string($entry) && $entry !== ''))));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTags(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $tags = [];
        $seen = [];

        foreach ($value as $entry) {
            if (! is_string($entry) || $entry === '') {
                continue;
            }

            $normalizedKey = mb_strtolower($entry);

            if (isset($seen[$normalizedKey])) {
                continue;
            }

            $seen[$normalizedKey] = true;
            $tags[] = $entry;
        }

        return $tags;
    }

    private function filledArray(string $field): bool
    {
        $value = $this->input($field);

        return is_array($value) && $value !== [];
    }
}
