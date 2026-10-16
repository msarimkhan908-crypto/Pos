<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class DesktopController extends Controller
{
    /**
     * GET /desktop/sync-status
     * Returns last sync info as JSON (consumed by the sidebar).
     */
    public function syncStatus(): JsonResponse
    {
        $path = storage_path('app/last_sync.json');

        if (! file_exists($path)) {
            return response()->json(['status' => 'never']);
        }

        $data = json_decode(file_get_contents($path), true) ?? [];

        return response()->json($data);
    }

    /**
     * POST /desktop/sync
     * Triggers pull + push and redirects back with a flash message.
     */
    public function sync(): RedirectResponse
    {
        $pullExit = Artisan::call('sync:pull');
        $pushExit = Artisan::call('sync:push');

        if ($pullExit === 0 && $pushExit === 0) {
            return back()->with('success', __('Synced with server successfully.'));
        }

        return back()->with('error', __('Sync failed. Check that the server URL and token are correct.'));
    }
}
