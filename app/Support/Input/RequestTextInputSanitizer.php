<?php

namespace App\Support\Input;

use Illuminate\Http\Request;

final class RequestTextInputSanitizer
{
    public function __construct(
        private readonly TextInputSanitizer $textInputSanitizer,
    ) {}

    /**
     * @param  array<int, string>  $singleLineFields
     * @param  array<int, string>  $multilineFields
     */
    public function sanitize(Request $request, array $singleLineFields = [], array $multilineFields = []): void
    {
        $data = $request->all();

        foreach ($singleLineFields as $field) {
            $data = $this->sanitizePath(
                $data,
                explode('.', $field),
                fn (?string $value): ?string => $this->textInputSanitizer->sanitizeSingleLine($value),
            );
        }

        foreach ($multilineFields as $field) {
            $data = $this->sanitizePath(
                $data,
                explode('.', $field),
                fn (?string $value): ?string => $this->textInputSanitizer->sanitizeMultiline($value),
            );
        }

        $request->merge($data);
    }

    /**
     * @param  array<string, mixed>|list<mixed>|mixed  $data
     * @param  array<int, string>  $segments
     * @param  callable(?string): ?string  $sanitizer
     * @return array<string, mixed>|list<mixed>|mixed
     */
    private function sanitizePath(mixed $data, array $segments, callable $sanitizer): mixed
    {
        if ($segments === []) {
            return $this->sanitizeValue($data, $sanitizer);
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            if (! is_array($data)) {
                return $data;
            }

            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitizePath($value, $segments, $sanitizer);
            }

            return $data;
        }

        if (! is_array($data) || ! array_key_exists($segment, $data)) {
            return $data;
        }

        $data[$segment] = $this->sanitizePath($data[$segment], $segments, $sanitizer);

        return $data;
    }

    /**
     * @param  callable(?string): ?string  $sanitizer
     */
    private function sanitizeValue(mixed $value, callable $sanitizer): mixed
    {
        if (is_string($value)) {
            return $sanitizer($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $entry) {
            $value[$key] = $this->sanitizeValue($entry, $sanitizer);
        }

        return $value;
    }
}
