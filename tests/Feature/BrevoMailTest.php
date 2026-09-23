<?php

use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;

test('brevo mailer requires a private key and uses the API transport', function () {
    config(['services.brevo.key' => '']);
    expect(fn () => Mail::mailer('brevo'))->toThrow(RuntimeException::class, 'BREVO_API_KEY is missing');
    config(['services.brevo.key' => 'test-only-key']);
    expect(Mail::mailer('brevo')->getSymfonyTransport())->toBeInstanceOf(BrevoApiTransport::class);
});

test('brevo sends Laravel mail through HTTPS with recipient and content intact', function () {
    config(['services.brevo.key' => 'test-only-key']);
    $client = new MockHttpClient(function ($method, $url, $options) {
        expect($method)->toBe('POST');
        expect($url)->toBe('https://api.brevo.com/v3/smtp/email');
        $payload = json_decode($options['body'], true);
        expect($payload['to'][0]['email'])->toBe('guest@example.com');
        expect($payload['subject'])->toBe('Carolina test');
        expect($payload['textContent'])->toBe('Verification delivery test');

        return new MockResponse('{"messageId":"test-message"}', ['http_code' => 201]);
    });
    $mailer = Mail::mailer('brevo');
    $mailer->setSymfonyTransport(new BrevoApiTransport('test-only-key', $client));
    $mailer->raw('Verification delivery test', fn ($message) => $message->to('guest@example.com')->subject('Carolina test'));
});

test('brevo rejection is not reported as a successful send', function () {
    config(['services.brevo.key' => 'test-only-key']);
    $mailer = Mail::mailer('brevo');
    $mailer->setSymfonyTransport(new BrevoApiTransport('test-only-key', new MockHttpClient(
        new MockResponse('{"message":"Sender not approved"}', ['http_code' => 400])
    )));
    expect(fn () => $mailer->raw('test', fn ($message) => $message->to('guest@example.com')->subject('test')))
        ->toThrow(HttpTransportException::class, 'Sender not approved');
});
