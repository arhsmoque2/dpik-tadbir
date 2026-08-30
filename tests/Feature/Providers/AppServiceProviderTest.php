<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

test('app service provider forces https scheme in production environment', function () {
    app()['env'] = 'production';

    $provider = new AppServiceProvider(app());
    $provider->boot();

    expect(URL::to('/admin/login'))->toStartWith('https://');
});

test('app service provider does not force https in testing environment', function () {
    app()['env'] = 'testing';
    URL::forceScheme(null);

    $provider = new AppServiceProvider(app());
    $provider->boot();

    expect(app()->environment('production'))->toBeFalse();
});
