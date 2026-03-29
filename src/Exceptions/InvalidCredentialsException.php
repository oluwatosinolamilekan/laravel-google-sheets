<?php

namespace Olamilekan\GoogleSheets\Exceptions;

class InvalidCredentialsException extends GoogleSheetsException
{
    public static function fileNotFound(string $path): static
    {
        return new static("Google Sheets credentials file not found at [{$path}].");
    }

    public static function invalidFormat(): static
    {
        return new static('Google Sheets credentials file contains invalid JSON.');
    }
}
