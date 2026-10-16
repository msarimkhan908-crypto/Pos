<?php

use App\Support\ImageStorage;
use App\Support\Printing;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// A valid 1×1 PNG (base64)
const PRINTING_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
});

// ── Printing::receipt ──────────────────────────────────────────────────────

test('Printing::receipt returns config and business arrays', function () {
    $result = Printing::receipt();

    expect($result)->toHaveKeys(['config', 'business', 'headerImage'])
        ->and($result['config'])->toBeArray()
        ->and($result['business'])->toBeArray();
});

test('Printing::receipt returns null headerImage when no logo is set', function () {
    settings()->set(['business.logo' => null, 'receipt.header_image' => null]);

    $result = Printing::receipt();

    expect($result['headerImage'])->toBeNull();
});

test('Printing::receipt falls back to ImageStorage::url() when dataUrl returns null', function () {
    // Set a logo path that doesn't exist on disk → dataUrl() returns null → fallback URL
    settings()->set(['business.logo' => 'settings/missing-logo.png']);

    $result = Printing::receipt();

    // Falls back to the /storage/ URL
    expect($result['headerImage'])->toBe('/storage/settings/missing-logo.png');
});

test('Printing::receipt accepts a pre-built headerImage string (live preview)', function () {
    $image = 'data:image/png;base64,abc123';
    $result = Printing::receipt(null, $image);

    expect($result['headerImage'])->toBe($image);
});

test('Printing::receipt uses a stored file as base64 when it exists', function () {
    // Write a real file to the actual public disk directory so dataUrl() can read it
    $png = base64_decode(PRINTING_PNG);
    $relPath = 'settings/logo.png';

    // Use the real disk (not faked) for this test so dataUrl() resolves the path correctly
    $fullPath = storage_path('app/public/'.$relPath);
    is_dir(dirname($fullPath)) || mkdir(dirname($fullPath), 0755, true);
    file_put_contents($fullPath, $png);

    settings()->set(['business.logo' => $relPath]);

    $result = Printing::receipt();
    @unlink($fullPath);

    expect($result['headerImage'])->toStartWith('data:image/');
});

// ── Printing::dataUrl ──────────────────────────────────────────────────────

test('Printing::dataUrl returns null for a missing file without throwing', function () {
    $result = Printing::dataUrl('settings/no-file.png');

    expect($result)->toBeNull();
});

test('Printing::dataUrl returns a data URL for a real file on disk', function () {
    $png = base64_decode(PRINTING_PNG);
    $relPath = 'settings/dataurl-test.png';
    $fullPath = storage_path('app/public/'.$relPath);
    is_dir(dirname($fullPath)) || mkdir(dirname($fullPath), 0755, true);
    file_put_contents($fullPath, $png);

    $result = Printing::dataUrl($relPath);
    @unlink($fullPath);

    expect($result)->toStartWith('data:image/');
});

// ── ImageStorage ───────────────────────────────────────────────────────────

test('ImageStorage stores uploads on the public disk and returns the relative path', function () {
    $file = UploadedFile::fake()->image('logo.png', 200, 80);
    $path = ImageStorage::replace($file, null, 'settings');

    expect($path)->not->toBeNull()
        ->and($path)->toContain('settings/')
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

test('ImageStorage::url returns a /storage/ URL', function () {
    $url = ImageStorage::url('settings/logo.png');

    expect($url)->toBe('/storage/settings/logo.png');
});

test('ImageStorage::replace deletes the old file when a new one is uploaded', function () {
    Storage::disk('public')->put('settings/old.png', 'old');

    $new = UploadedFile::fake()->image('new.png', 100, 100);
    $path = ImageStorage::replace($new, 'settings/old.png', 'settings');

    Storage::disk('public')->assertMissing('settings/old.png');
    Storage::disk('public')->assertExists($path);
});

test('ImageStorage::replace returns null and deletes old file when remove=true', function () {
    Storage::disk('public')->put('settings/removeme.png', 'data');

    $result = ImageStorage::replace(null, 'settings/removeme.png', 'settings', true);

    expect($result)->toBeNull();
    Storage::disk('public')->assertMissing('settings/removeme.png');
});
