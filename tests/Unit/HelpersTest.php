<?php

/**
 * uams_format_phone_schema() and uams_day_abbreviation(): pure string helpers.
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
