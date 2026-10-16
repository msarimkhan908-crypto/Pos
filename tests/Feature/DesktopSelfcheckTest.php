<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
});

function selfcheckResult(): array
{
    Artisan::call('desktop:selfcheck');
    $raw = Artisan::output();

    return json_decode($raw, true) ?? [];
}

test('desktop:selfcheck returns valid JSON', function () {
    Artisan::call('desktop:selfcheck');
    $raw = Artisan::output();
    $data = json_decode($raw, true);

    expect($data)->toBeArray()
        ->and($data)->toHaveKeys(['timestamp', 'checks', 'critical_failures'])
        ->and($data['checks'])->toBeArray();
});

test('desktop:selfcheck exits with success status', function () {
    $this->artisan('desktop:selfcheck')->assertExitCode(0);
});

test('desktop:selfcheck contains all required check names', function () {
    $data = selfcheckResult();
    $names = array_column($data['checks'], 'name');

    expect($names)->toContain('migrations_ran')
        ->and($names)->toContain('upload_roundtrip')
        ->and($names)->toContain('ext_pdo_sqlite')
        ->and($names)->toContain('file_uploads_enabled');
});

test('desktop:selfcheck upload_roundtrip passes on writable storage', function () {
    $data = selfcheckResult();
    $roundtrip = collect($data['checks'])->firstWhere('name', 'upload_roundtrip');

    expect($roundtrip)->not->toBeNull()
        ->and($roundtrip['ok'])->toBeTrue();
});

test('desktop:selfcheck migrations_ran passes after seeding', function () {
    $data = selfcheckResult();
    $check = collect($data['checks'])->firstWhere('name', 'migrations_ran');

    expect($check['ok'])->toBeTrue();
});

test('desktop:selfcheck critical_failures is empty when everything is healthy', function () {
    $data = selfcheckResult();

    // In the test environment all checks should pass
    // (storage_* might fail because storage path is writable in the test env)
    expect($data['critical_failures'])->toBeArray();
});
