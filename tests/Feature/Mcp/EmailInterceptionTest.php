<?php

use Illuminate\Support\Facades\Mail;
use KirschbaumDevelopment\MailIntercept\WithMailInterceptor;

uses(WithMailInterceptor::class);

test('mail interceptor captures outbound emails without network or mock drift', function () {
    $this->interceptMail();

    Mail::raw('Sila rujuk dokumen kemajuan projek jambatan Sungai Udang.', function ($message) {
        $message->to('jkr_sarawak@jkr.gov.my')
            ->subject('Makluman Interim Claim 4 - PC-2023-011');
    });

    $mails = $this->interceptedMail();
    expect($mails)->toHaveCount(1);

    $mail = $mails->first();
    $this->assertMailSentTo('jkr_sarawak@jkr.gov.my', $mail);
    $this->assertMailSubject('Makluman Interim Claim 4 - PC-2023-011', $mail);
    $this->assertMailBodyContainsString('Sila rujuk dokumen kemajuan projek', $mail);
});
