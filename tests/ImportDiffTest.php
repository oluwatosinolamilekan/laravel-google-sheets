<?php

namespace Olamilekan\GoogleSheets\Tests;

use Olamilekan\GoogleSheets\Testing\FakeSheet;
use Orchestra\Testbench\TestCase;

class ImportDiffTest extends TestCase
{
    public function test_it_previews_new_changed_deleted_invalid_and_conflict_rows(): void
    {
        $sheet = new FakeSheet([
            ['email' => 'new@example.com', 'name' => 'New User', 'role' => 'user'],
            ['email' => 'changed@example.com', 'name' => 'Changed User', 'role' => 'owner'],
            ['email' => 'invalid-email', 'name' => 'Invalid User', 'role' => 'user'],
            ['email' => 'duplicate@example.com', 'name' => 'First Duplicate', 'role' => 'user'],
            ['email' => 'duplicate@example.com', 'name' => 'Second Duplicate', 'role' => 'user'],
            ['email' => '', 'name' => 'Missing Key', 'role' => 'user'],
        ]);

        $target = collect([
            ['email' => 'changed@example.com', 'name' => 'Changed User', 'role' => 'user'],
            ['email' => 'deleted@example.com', 'name' => 'Deleted User', 'role' => 'user'],
        ]);

        $preview = $sheet
            ->diffAgainst($target, key: 'email')
            ->rules(['email' => ['required', 'email']])
            ->preview();

        $this->assertSame([
            'new' => 1,
            'changed' => 1,
            'deleted' => 1,
            'invalid' => 2,
            'conflicts' => 2,
        ], $preview->counts());

        $this->assertSame('new@example.com', $preview->new->first()['email']);
        $this->assertSame('changed@example.com', $preview->changed->first()['key']);
        $this->assertSame('owner', $preview->changed->first()['changes']['role']['to']);
        $this->assertSame('deleted@example.com', $preview->deleted->first()['email']);
        $this->assertTrue($preview->hasChanges());
    }

    public function test_it_can_limit_columns_used_for_changed_rows(): void
    {
        $sheet = new FakeSheet([
            ['email' => 'user@example.com', 'name' => 'Same Name', 'role' => 'owner'],
        ]);

        $target = [
            ['email' => 'user@example.com', 'name' => 'Same Name', 'role' => 'user'],
        ];

        $preview = $sheet
            ->diffAgainst($target, key: 'email')
            ->only(['name'])
            ->preview();

        $this->assertSame(0, $preview->changed->count());
    }
}
