<?php

use Symfony\Component\Process\Process;

test('the production router serves the homepage module as executable JavaScript', function () {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    expect($socket)->not->toBeFalse();
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $root = dirname(__DIR__, 2);
    $server = new Process([PHP_BINARY, '-S', $address, '-t', 'public', 'docker/router.php'], $root);
    $server->setTimeout(5);
    $server->start();

    try {
        $ready = $server->waitUntil(fn ($type, $output) => str_contains($output, 'Development Server'));
        expect($ready)->toBeTrue();
        $module = file_get_contents('http://'.$address.'/js/home-availability.mjs', false, stream_context_create(['http' => ['timeout' => 2]]));
        expect(implode("\n", $http_response_header))->toContain('Content-Type: application/javascript')
            ->and($module)->toContain('export function formatBookedSlot');
    } finally {
        $server->stop();
    }
});
