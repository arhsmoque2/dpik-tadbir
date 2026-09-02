<?php

declare(strict_types=1);

test('actions are final readonly and declare an execute method', function () {
    expect('App\Actions')
        ->classes()
        ->toBeFinal()
        ->toBeReadonly()
        ->toHaveMethod('execute');
});

test('models extend eloquent base model', function () {
    expect('App\Models')
        ->classes()
        ->toExtend('Illuminate\Database\Eloquent\Model');
});

test('support classes are final and readonly', function () {
    expect('App\Support')
        ->classes()
        ->toBeFinal()
        ->toBeReadonly();
});
