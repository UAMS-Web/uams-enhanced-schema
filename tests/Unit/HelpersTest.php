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
    'a negative integer' => [-4],
    'zero' => [0],
    'zero text' => ['0'],
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

it('reads text from a string, a number or nothing', function (mixed $value, string $expected): void {
    expect(uams_text($value))->toBe($expected);
})->with([
    'a string' => ['72205', '72205'],
    'an integer' => [72205, '72205'],
    'a float' => [4.5, '4.5'],
    'zero' => [0, '0'],
    'a boolean' => [true, ''],
    'null' => [null, ''],
    'an array' => [['x'], ''],
]);

it('reads a number from a number or numeric text, and zero from anything else', function (mixed $value, int|float $expected): void {
    expect(uams_number($value))->toBe($expected);
})->with([
    'an integer' => [42, 42],
    'a float' => [4.5, 4.5],
    'integer text' => ['42', 42],
    'decimal text' => ['4.5', 4.5],
    'a word' => ['many', 0],
    'a word with a dot' => ['many.words', 0],
    'a boolean' => [true, 0],
    'null' => [null, 0],
    'an array' => [[3], 0],
]);

it('reads an id from an integer or digit-only text, and zero from anything else', function (mixed $value, int $expected): void {
    expect(uams_id($value))->toBe($expected);
})->with([
    'an integer' => [7, 7],
    'a negative integer' => [-7, -7],
    'digit text' => ['7', 7],
    'a word' => ['seven', 0],
    'a decimal' => ['7.5', 0],
    'empty text' => ['', 0],
    'a float' => [7.0, 0],
    'null' => [null, 0],
]);

it('reads an array as it is and anything else as an empty array', function (mixed $value, array $expected): void {
    expect(uams_array($value))->toBe($expected);
})->with([
    'an array' => [['a' => 1], ['a' => 1]],
    'an empty array' => [[], []],
    'text' => ['x', []],
    'false' => [false, []],
    'null' => [null, []],
]);

it('reads a nested value out of arrays and objects', function (): void {
    $tree = (object) ['data' => ['entities' => [(object) ['rating' => (object) ['value' => 4.5]]]]];

    expect(uams_dig($tree, 'data', 'entities', 0, 'rating', 'value'))->toBe(4.5)
        ->and(uams_dig(['a' => ['b' => 'c']], 'a', 'b'))->toBe('c')
        ->and(uams_dig($tree))->toBe($tree);
});

it('reads null where a step of the path is missing or not a structure', function (mixed $tree): void {
    expect(uams_dig($tree, 'data', 'entities', 0))->toBeNull();
})->with([
    'a missing key' => [(object) ['data' => []]],
    'an array missing the key' => [['data' => ['entities' => []]]],
    'a scalar in the way' => [['data' => 'text']],
    'null' => [null],
]);

it('formats a date as YYYY-MM-DD', function (): void {
    expect(uams_calendar_date('December 26, 2030'))->toBe('2030-12-26');
});

it('returns null for text that is not a date', function (string $input): void {
    expect(uams_calendar_date($input))->toBeNull();
})->with([
    'empty' => [''],
    'a word' => ['someday'],
]);

it('reads an ACF field as text for the given post', function (): void {
    Brain\Monkey\Functions\expect('get_field')->once()->with('physician_npi', 12)->andReturn(1234567890);

    expect(uams_field_text('physician_npi', 12))->toBe('1234567890');
});

it('reads an ACF field for the current post when no post is given', function (): void {
    Brain\Monkey\Functions\expect('get_field')->once()->with('physician_npi', false)->andReturn('x');

    expect(uams_field_text('physician_npi'))->toBe('x');
});

it('joins the name parts that are not empty with single spaces', function (string $first, string $middle, string $last, string $expected): void {
    expect(uams_full_name($first, $middle, $last))->toBe($expected);
})->with([
    'all three' => ['Ada', 'M.', 'Lovelace', 'Ada M. Lovelace'],
    'no middle name' => ['Ada', '', 'Lovelace', 'Ada Lovelace'],
    'no first name' => ['', 'M.', 'Lovelace', 'M. Lovelace'],
    'no last name' => ['Ada', 'M.', '', 'Ada M.'],
    'a name of 0' => ['0', '', 'Lovelace', '0 Lovelace'],
    'none' => ['', '', '', ''],
]);
