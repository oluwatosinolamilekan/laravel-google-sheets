<?php

namespace Olamilekan\GoogleSheets\Exceptions;

class InvalidConnectionException extends GoogleSheetsException
{
    public static function connectionNotFound(string $name): static
    {
        return new static("Google Sheets connection [{$name}] is not configured.");
    }

    public static function missingSpreadsheetId(string $name): static
    {
        return new static("Spreadsheet ID is missing for connection [{$name}].");
    }
}
