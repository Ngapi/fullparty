<?php

use App\Support\Input\RequestTextInputSanitizer;
use Illuminate\Http\Request;

it('sanitizes nested single-line and multiline request fields with wildcards', function () {
    $request = Request::create('/', 'POST', [
        'username' => "  H\u{200B}ello   world  ",
        'notes' => " Line one\u{200B}\r\n\r\nSecond\tline ",
        'guest_applicant' => [
            'name' => "  W\u{200B}arrior   Light  ",
        ],
        'answers' => [
            'experience' => " First line\r\nSecond\u{200B} line ",
            'aliases' => ['  One  ', " T\u{200B}wo "],
        ],
    ]);

    app(RequestTextInputSanitizer::class)->sanitize(
        $request,
        ['username', 'guest_applicant.name', 'answers.aliases.*'],
        ['notes', 'answers.experience'],
    );

    expect($request->input('username'))->toBe('Hello world')
        ->and($request->input('notes'))->toBe("Line one\n\nSecond line")
        ->and($request->input('guest_applicant.name'))->toBe('Warrior Light')
        ->and($request->input('answers.experience'))->toBe("First line\nSecond line")
        ->and($request->input('answers.aliases'))->toBe(['One', 'Two']);
});
