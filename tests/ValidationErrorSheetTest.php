<?php

namespace Olamilekan\GoogleSheets\Tests;

use Illuminate\Validation\ValidationException;
use Olamilekan\GoogleSheets\Imports\SheetImport;
use Olamilekan\GoogleSheets\Testing\FakeSheet;
use Orchestra\Testbench\TestCase;

class ValidationErrorSheetTest extends TestCase
{
    public function test_it_writes_human_friendly_validation_errors_to_a_sheet(): void
    {
        $sheet = new FakeSheet([
            ['name' => 'Ada', 'email' => 'ada@example.com'],
            ['name' => '', 'email' => 'invalid-email'],
            ['name' => 'Grace', 'email' => ''],
        ]);

        try {
            $sheet->validateWithErrorSheet([
                'name' => ['required'],
                'email' => ['required', 'email'],
            ]);

            $this->fail('Expected validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('Import Errors', $sheet->errorSheets);
            $this->assertSame([
                ['Row', 'Field', 'Message'],
                [3, 'name', 'The name field is required.'],
                [3, 'email', 'The email field must be a valid email address.'],
                [4, 'email', 'The email field is required.'],
            ], $sheet->errorSheets['Import Errors']);

            $this->assertArrayHasKey('row_3.name', $exception->errors());
            $this->assertArrayHasKey('row_4.email', $exception->errors());
        }
    }

    public function test_it_returns_validated_rows_without_writing_an_error_sheet_when_rows_are_valid(): void
    {
        $sheet = new FakeSheet([
            ['name' => 'Ada', 'email' => 'ada@example.com'],
        ]);

        $rows = $sheet->validateWithErrorSheet([
            'name' => ['required'],
            'email' => ['required', 'email'],
        ]);

        $this->assertSame([
            ['name' => 'Ada', 'email' => 'ada@example.com'],
        ], $rows->all());
        $this->assertSame([], $sheet->errorSheets);
    }

    public function test_imports_can_opt_into_validation_error_sheets(): void
    {
        $sheet = new FakeSheet([
            ['email' => 'invalid-email'],
        ]);

        $import = new class extends SheetImport {
            public ?string $errorSheet = 'Import Errors';

            public function rules(): array
            {
                return ['email' => ['required', 'email']];
            }
        };

        try {
            $sheet->import($import);

            $this->fail('Expected validation to fail.');
        } catch (ValidationException) {
            $this->assertSame([
                ['Row', 'Field', 'Message'],
                [2, 'email', 'The email field must be a valid email address.'],
            ], $sheet->errorSheets['Import Errors']);
        }
    }
}
