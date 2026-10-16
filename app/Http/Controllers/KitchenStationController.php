<?php

namespace App\Http\Controllers;

use App\Http\Requests\KitchenStationRequest;
use App\Models\KitchenStation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Kitchen stations (Main Kitchen, Grill, Pizza, Drinks, Dessert…) are configuration:
 * listed with settings.view, changed with settings.manage.
 */
class KitchenStationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings.view', only: ['index']),
            new Middleware('permission:settings.manage', except: ['index']),
        ];
    }

    public function index(): View
    {
        $stations = KitchenStation::query()->ordered()->withCount(['categories', 'products'])->get();

        return view('kitchen.stations.index', compact('stations'));
    }

    public function create(): View
    {
        return view('kitchen.stations.create', ['station' => new KitchenStation]);
    }

    public function store(KitchenStationRequest $request): RedirectResponse
    {
        $station = KitchenStation::create($request->stationData());

        return redirect()->route('kitchen.stations.index')->with('success', __('Station ":name" created.', ['name' => $station->name]));
    }

    public function edit(KitchenStation $station): View
    {
        return view('kitchen.stations.edit', compact('station'));
    }

    public function update(KitchenStationRequest $request, KitchenStation $station): RedirectResponse
    {
        $station->update($request->stationData());

        return redirect()->route('kitchen.stations.index')->with('success', __('Station ":name" updated.', ['name' => $station->name]));
    }

    /**
     * Categories and products lose the link (null on delete); past KOT items keep working.
     */
    public function destroy(KitchenStation $station): RedirectResponse
    {
        $station->delete();

        return redirect()->route('kitchen.stations.index')->with('success', __('Station ":name" deleted.', ['name' => $station->name]));
    }
}
