<?php

/**
 * The pure helpers: phone and day formatting, clock times, and the typed readers
 * for ACF field values (ids, repeater rows, text).
 */

declare(strict_types=1);

it('formats a ten-digit number whatever its punctuation', function (string $input): void {
    expect(uams_format_phone_schema($input))->toBe('+1-501-686-7000');
})->with([
    'bare digits' => ['5016867000'],
    'parentheses' => ['(501) 686-7000'],
    'dots' => ['501.686.7000'],
    'spaces' => ['501 686 7000'],
]);

it('drops a leading country code from an eleven-digit number', function (): void {
    expect(uams_format_phone_schema('1-501-686-7000'))->toBe('+1-501-686-7000');
});

it('returns the input unchanged when it is not a US number', function (string $input): void {
    expect(uams_format_phone_schema($input))->toBe($input);
})->with([
    'eleven digits not starting with 1' => ['25016867000'],
    'too short' => ['686-7000'],
    'too long' => ['150168670001'],
    'empty' => [''],
    'letters only' => ['call us'],
]);

it('abbreviates each day name', function (string $day, string $abbreviation): void {
    expect(uams_day_abbreviation($day))->toBe($abbreviation);
})->with([
    ['Monday', 'Mo'],
    ['Tuesday', 'Tu'],
    ['Wednesday', 'We'],
    ['Thursday', 'Th'],
    ['Friday', 'Fr'],
    ['Saturday', 'Sa'],
    ['Sunday', 'Su'],
    ['Mon - Fri', 'Mo-Fr'],
]);

it('returns an empty string for an unknown day', function (string $day): void {
    expect(uams_day_abbreviation($day))->toBe('');
})->with([
    'a typo' => ['Funday'],
    'lower case' => ['monday'],
    'empty' => [''],
]);

it('keeps integer ids and digit-only strings, as integers, in order', function (): void {
    expect(uams_id_list([5, '12', 7]))->toBe([5, 12, 7]);
});

it('drops every id that is not an integer or a digit-only string', function (mixed $id): void {
    expect(uams_id_list([$id]))->toBe([]);
})->with([
    'a float' => [1.5],
    'a boolean' => [true],
    'null' => [null],
    'an array' => [[3]],
    'an object' => [new stdClass],
    'a word' => ['abc'],
    'a negative number' => ['-4'],
    'a decimal' => ['4.5'],
    'an empty string' => [''],
]);

it('reads no ids from a value that is not a list', function (mixed $value): void {
    expect(uams_id_list($value))->toBe([]);
})->with([
    'false' => [false],
    'null' => [null],
    'a string' => ['31'],
    'an integer' => [31],
    'an empty list' => [[]],
]);

it('keeps the rows of a repeater and skips anything that is not a row', function (): void {
    expect(uams_rows([['text' => 'a'], 'x', 4, null, ['text' => 'b']]))->toBe([['text' => 'a'], ['text' => 'b']]);
});

it('reads no rows from a value that is not a list', function (mixed $value): void {
    expect(uams_rows($value))->toBe([]);
})->with([
    'false' => [false],
    'null' => [null],
    'a string' => ['row'],
]);

it('reads a string as it is and anything else as an empty string', function (mixed $value, string $expected): void {
    expect(uams_string($value))->toBe($expected);
})->with([
    'text' => ['Heart', 'Heart'],
    'zero text' => ['0', '0'],
    'an integer' => [5, ''],
    'null' => [null, ''],
    'an array' => [['x'], ''],
]);

it('formats a time of day as 24-hour HH:MM', function (string $input, string $expected): void {
    expect(uams_clock_time($input))->toBe($expected);
})->with([
    'morning' => ['8:00 am', '08:00'],
    'afternoon' => ['4:15 pm', '16:15'],
    'already 24-hour' => ['23:45', '23:45'],
]);

it('returns null for text that is not a time', function (string $input): void {
    expect(uams_clock_time($input))->toBeNull();
})->with([
    'empty' => [''],
    'a word' => ['closed'],
]);
