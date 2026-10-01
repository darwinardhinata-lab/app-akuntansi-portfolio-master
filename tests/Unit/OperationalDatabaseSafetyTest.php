<?php

namespace Tests\Unit;

use App\Support\OperationalDatabaseSafety;
use PHPUnit\Framework\TestCase;

class OperationalDatabaseSafetyTest extends TestCase
{
    public function test_operational_mgi_database_blocks_destructive_commands_by_default(): void
    {
        $this->assertTrue(OperationalDatabaseSafety::prohibitsDestructiveCommands('mgi_fresh_20260924', 'local', false));
    }

    public function test_testing_and_explicit_operator_opt_in_are_allowed(): void
    {
        $this->assertFalse(OperationalDatabaseSafety::prohibitsDestructiveCommands('mgi_fresh_20260924', 'testing', false));
        $this->assertFalse(OperationalDatabaseSafety::prohibitsDestructiveCommands('mgi_fresh_20260924', 'local', true));
    }

    public function test_string_false_does_not_accidentally_enable_the_destructive_command_escape_hatch(): void
    {
        $this->assertTrue(OperationalDatabaseSafety::prohibitsDestructiveCommands('mgi_fresh_20260924', 'local', filter_var('false', FILTER_VALIDATE_BOOLEAN)));
    }

    public function test_legacy_and_fixture_database_names_are_not_blocked_by_this_mgi_guard(): void
    {
        $this->assertFalse(OperationalDatabaseSafety::prohibitsDestructiveCommands('db_akuntansi', 'local', false));
        $this->assertFalse(OperationalDatabaseSafety::prohibitsDestructiveCommands('mgi_fresh_a3test_fixture', 'testing', false));
    }
}
