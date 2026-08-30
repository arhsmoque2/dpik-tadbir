<?php

use App\Http\Middleware\RegistrationWhitelistMiddleware;
use App\Models\AllowedRegistrationEmail;
use App\Services\Auth\RegistrationWhitelistService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('whitelisted email is accepted by service', function () {
    $service = app(RegistrationWhitelistService::class);

    AllowedRegistrationEmail::create([
        'email' => 'director@dpik.com.my',
        'notes' => 'Executive Director',
    ]);

    expect($service->isEmailAllowed('director@dpik.com.my'))->toBeTrue();
    expect($service->isEmailAllowed('DIRECTOR@DPIK.COM.MY'))->toBeTrue();
});

test('non-whitelisted email is rejected by service', function () {
    $service = app(RegistrationWhitelistService::class);

    expect($service->isEmailAllowed('unauthorized.user@external.com'))->toBeFalse();
});

test('middleware throws 403 on non-whitelisted email', function () {
    $middleware = app(RegistrationWhitelistMiddleware::class);
    $request = Request::create('/register', 'POST', ['email' => 'intruder@random.com']);

    $middleware->handle($request, function () {
        return response('OK');
    });
})->throws(HttpException::class);

test('middleware allows whitelisted email to proceed', function () {
    $service = app(RegistrationWhitelistService::class);
    $service->whitelistEmail('allowed.exec@dpik.com.my', 'Valid whitelist test');

    $middleware = app(RegistrationWhitelistMiddleware::class);
    $request = Request::create('/register', 'POST', ['email' => 'allowed.exec@dpik.com.my']);

    $response = $middleware->handle($request, function () {
        return response('PASSED');
    });

    expect($response->getContent())->toBe('PASSED');
});
