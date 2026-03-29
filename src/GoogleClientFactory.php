<?php

namespace Olamilekan\GoogleSheets;

use Google\Client as GoogleClient;
use Olamilekan\GoogleSheets\Exceptions\InvalidCredentialsException;

class GoogleClientFactory
{
    public static function make(array $config): GoogleClient
    {
        $client = new GoogleClient();
        $client->setApplicationName($config['application_name'] ?? 'Laravel Google Sheets');
        $client->setScopes($config['scopes'] ?? [\Google\Service\Sheets::SPREADSHEETS]);

        $credentials = $config['credentials'] ?? [];
        $type = $credentials['type'] ?? 'service_account';

        if ($type === 'service_account') {
            static::authenticateServiceAccount($client, $credentials);
        }

        return $client;
    }

    protected static function authenticateServiceAccount(GoogleClient $client, array $credentials): void
    {
        $path = $credentials['file'] ?? null;

        if (! $path || ! file_exists($path)) {
            throw InvalidCredentialsException::fileNotFound($path ?? 'null');
        }

        $json = json_decode(file_get_contents($path), true);

        if (! is_array($json)) {
            throw InvalidCredentialsException::invalidFormat();
        }

        $client->setAuthConfig($json);
    }
}
