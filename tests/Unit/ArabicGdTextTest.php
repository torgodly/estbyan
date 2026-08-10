<?php

use App\Support\ArabicGdText;

it('leaves latin and digits unchanged', function () {
    expect(ArabicGdText::forGd('SC26-00123'))->toBe('SC26-00123')
        ->and(ArabicGdText::forGd('119761234567'))->toBe('119761234567');
});

it('reshapes arabic text for gd rendering', function () {
    $prepared = ArabicGdText::forGd('رقم المرجع');

    expect($prepared)->not->toBe('رقم المرجع')
        ->and(mb_strlen($prepared))->toBe(mb_strlen('رقم المرجع'));
});
