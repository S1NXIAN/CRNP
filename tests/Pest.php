<?php

declare(strict_types=1);

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RestRtdbClient;
use App\Rtdb\ServiceAccountTokenProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

const RTDB_BASE_URL = 'https://crnp-test.firebaseio.com';

const RTDB_TOKEN_URL = 'https://oauth2.googleapis.com/token';

/**
 * A one-line service-account JSON with a freshly minted RSA key, so the REST
 * client can sign a real RS256 assertion without any committed secret.
 */
function fakeServiceAccountPath(): string
{
    static $path = null;

    if ($path !== null) {
        return $path;
    }

    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    if ($key === false) {
        throw new RuntimeException('Unable to generate a test RSA key.');
    }

    $privateKey = '';
    openssl_pkey_export($key, $privateKey);

    $path = (string) tempnam(sys_get_temp_dir(), 'crnp-sa-');

    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'project_id' => 'crnp-test',
        'private_key_id' => 'test-key-id',
        'private_key' => $privateKey,
        'client_email' => 'rtdb-test@crnp-test.iam.gserviceaccount.com',
        'token_uri' => RTDB_TOKEN_URL,
    ], JSON_THROW_ON_ERROR));

    return $path;
}

function restRtdbClient(string $baseUrl = RTDB_BASE_URL): RestRtdbClient
{
    return new RestRtdbClient(
        $baseUrl,
        new ServiceAccountTokenProvider(fakeServiceAccountPath(), RTDB_TOKEN_URL, 3300),
    );
}

/**
 * Fake the token endpoint plus a set of RTDB URL => response pairs.
 *
 * @param  array<string, \Illuminate\Http\Client\Response>  $responses
 */
function fakeRtdb(array $responses): void
{
    Http::fake([
        RTDB_TOKEN_URL => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        ...$responses,
    ]);
}

/**
 * The query parameters of a sent request, decoded back to their raw values.
 *
 * @return array<string, string>
 */
function httpQuery(Request $request): array
{
    $query = [];
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query;
}

function rtdb(): RtdbClient
{
    return app(RtdbClient::class);
}
