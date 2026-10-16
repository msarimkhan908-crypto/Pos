<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard the database before traits such as RefreshDatabase run migrate:fresh.
     */
    protected function setUpTraits()
    {
        $this->ensureTestingDatabase();

        return parent::setUpTraits();
    }

    /**
     * Refuse to run against anything other than the in-memory SQLite database
     * from phpunit.xml, e.g. when a cached config (php artisan config:cache)
     * would otherwise point the tests at the MySQL development database.
     */
    protected function ensureTestingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run tests against database [{$connection}:{$database}]. Tests must use in-memory SQLite. "
                .'Run "php artisan config:clear" and check phpunit.xml.'
            );
        }
    }
}
