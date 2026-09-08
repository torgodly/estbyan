<?php

use App\Support\Code128Barcode;
use Tests\TestCase;

uses(TestCase::class);

it('encodes a known code 128 b checksum for HI', function () {
    expect(Code128Barcode::checksum('HI'))->toBe(20)
        ->and(Code128Barcode::symbols('HI'))->toBe([104, 40, 41, 20, 106]);
});

it('builds a scannable svg barcode for a registration reference', function () {
    $svg = Code128Barcode::svg('SC26-00001');

    expect($svg)->toStartWith('<svg')
        ->toContain('aria-label="SC26-00001"')
        ->toContain('preserveAspectRatio="none"')
        ->toContain('<rect ')
        ->and(substr_count($svg, '<rect '))->toBeGreaterThan(20)
        ->and(Code128Barcode::svg('SC26-00001'))->toBe($svg)
        ->and(Code128Barcode::svg('SC26-00002'))->not->toBe($svg);
});

it('rejects empty or non ascii values', function (string $value) {
    Code128Barcode::svg($value);
})->with(['', 'عبدالله'])->throws(InvalidArgumentException::class);
