<?php

namespace App\Support;

class OperationalDatabaseSafety
{
    public static function prohibitsDestructiveCommands(?string $database = null, ?string $environment = null, ?bool $allowDestructiveCommands = null): bool
    {
        $database ??= (string) config('database.connections.'.config('database.default').'.database');
        $environment ??= app()->environment();
        $allowDestructiveCommands ??= filter_var(
            config('platform.allow_destructive_database_commands', false),
            FILTER_VALIDATE_BOOLEAN,
        );

        return $environment !== 'testing'
            && str_starts_with(strtolower($database), 'mgi_fresh_')
            && ! $allowDestructiveCommands;
    }
}
