<?php

use App\Support\Input\TextInputSanitizer;

it('sanitizes single line text while preserving valid language characters', function () {
    $sanitizer = new TextInputSanitizer;
    $raw = "E\u{0301}quipe\u{00A0}\u{200B}Raid\x07";

    $expected = class_exists(Normalizer::class)
        ? 'Équipe Raid'
        : "E\u{0301}quipe Raid";

    expect($sanitizer->sanitizeSingleLine($raw))->toBe($expected);
});

it('sanitizes multiline text while preserving line breaks', function () {
    $sanitizer = new TextInputSanitizer;
    $raw = "Line\u{00A0}one\u{200B}\r\nSecond\u{202E} line\t";

    expect($sanitizer->sanitizeMultiline($raw))->toBe("Line one\nSecond line");
});
