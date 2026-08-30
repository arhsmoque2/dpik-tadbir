<?php

test('app does not leave debugging functions', function () {
    expect(['dd', 'dump', 'ray', 'var_dump'])
        ->not->toBeUsed();
});
