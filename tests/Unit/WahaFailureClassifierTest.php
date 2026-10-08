<?php

namespace Tests\Unit;

use App\Services\Notifications\WahaFailureClassifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response as ClientResponse;
use PHPUnit\Framework\TestCase;

class WahaFailureClassifierTest extends TestCase
{
    private function response(int $status, string $body = ''): ClientResponse
    {
        $factory = new Factory;
        $factory->fake([
            '*' => $factory::response($body === '' ? [] : $body, $status),
        ]);

        return $factory->get('https://example.test');
    }

    public function test_koneksi_timeout_memicu_breaker(): void
    {
        $classifier = new WahaFailureClassifier;

        $severity = $classifier->classify(null, new ConnectionException('cURL error 28: timed out'));

        $this->assertSame(WahaFailureClassifier::IMMEDIATE, $severity);
    }

    public function test_body_memuat_463_memicu_breaker(): void
    {
        $classifier = new WahaFailureClassifier;

        $severity = $classifier->classify($this->response(422, '{"error":"463 rate limited"}'), null);

        $this->assertSame(WahaFailureClassifier::IMMEDIATE, $severity);
    }

    public function test_http_5xx_dihitung_tapi_tidak_langsung_memicu(): void
    {
        $classifier = new WahaFailureClassifier;

        $severity = $classifier->classify($this->response(500), null);

        $this->assertSame(WahaFailureClassifier::COUNTS, $severity);
    }

    public function test_nomor_tidak_valid_bukan_breaker(): void
    {
        $classifier = new WahaFailureClassifier;

        $severity = $classifier->classify($this->response(422, '{"error":"nomor tidak terdaftar di WhatsApp"}'), null);

        $this->assertSame(WahaFailureClassifier::NONE, $severity);
    }
}
