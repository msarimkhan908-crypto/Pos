<?php

test('tests run against in-memory sqlite, never the development database', function () {
    expect(app()->environment())->toBe('testing')
        ->and(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');
});

test('running tests against a non-testing database is refused', function () {
    config(['database.default' => 'mysql']);

    expect(fn () => $this->ensureTestingDatabase())
        ->toThrow(RuntimeException::class, 'Refusing to run tests against database [mysql:');

    config(['database.default' => 'sqlite']);
});
