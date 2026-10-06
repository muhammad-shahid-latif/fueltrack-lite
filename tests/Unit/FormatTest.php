<?php

use App\Support\Format;

test('money drops decimals for whole amounts', function () {
    expect(Format::money(226800))->toBe('Rs. 226,800')
        ->and(Format::money('1245500.50'))->toBe('Rs. 1,245,500.50')
        ->and(Format::money(-2000))->toBe('-Rs. 2,000');
});

test('liters and meters are formatted for display', function () {
    expect(Format::liters(810))->toBe('810 L')
        ->and(Format::liters(68.225))->toBe('68.23 L')
        ->and(Format::meter(125430.5))->toBe('125430.50');
});
