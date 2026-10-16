<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

// A valid 1×1 PNG (base64)
const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    $this->actingAs(userWith('Admin'));
});

test('storage route serves an existing file with 200', function () {
    $png = base64_decode(PNG_1X1);
    Storage::disk('public')->put('settings/test.png', $png);

    $this->get('/storage/settings/test.png')->assertOk();
});

test('storage route returns correct MIME for a PNG', function () {
    Storage::disk('public')->put('settings/img.png', base64_decode(PNG_1X1));

    // The route returns the correct Content-Type header.
    // In a test environment fileinfo may use the extension fallback.
    $response = $this->get('/storage/settings/img.png')->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('image/');
});

test('storage route returns 404 for a missing file', function () {
    $this->get('/storage/settings/does-not-exist.png')->assertNotFound();
});

test('storage route blocks path traversal with ..', function () {
    $this->get('/storage/../.env')->assertNotFound();
    $this->get('/storage/settings/../../.env')->assertNotFound();
});

test('storage route sets cache headers', function () {
    Storage::disk('public')->put('settings/cached.png', base64_decode(PNG_1X1));

    $response = $this->get('/storage/settings/cached.png')->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('max-age')
        ->and($response->headers->has('ETag'))->toBeTrue();
});

test('storage route returns 304 when ETag matches', function () {
    Storage::disk('public')->put('settings/etag.png', base64_decode(PNG_1X1));

    $firstResponse = $this->get('/storage/settings/etag.png')->assertOk();
    $etag = $firstResponse->headers->get('ETag');

    $this->get('/storage/settings/etag.png', ['If-None-Match' => $etag])
        ->assertStatus(304);
});

test('storage route is accessible without authentication', function () {
    Storage::disk('public')->put('public-file.png', base64_decode(PNG_1X1));

    // Sign out and still be able to access the file
    auth()->logout();
    $this->get('/storage/public-file.png')->assertOk();
});
