<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModifierGroupRequest;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ModifierGroupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:products.view', only: ['index']),
            new Middleware('permission:products.create', only: ['create', 'store']),
            new Middleware('permission:products.edit', only: ['edit', 'update']),
            new Middleware('permission:products.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();

        $groups = ModifierGroup::query()
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->with('modifiers')
            ->withCount('products')
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('modifier-groups.index', compact('groups', 'search'));
    }

    public function create(): View
    {
        return view('modifier-groups.create', ['group' => new ModifierGroup(['is_active' => true, 'min_select' => 0])]);
    }

    public function store(ModifierGroupRequest $request): RedirectResponse
    {
        $group = DB::transaction(function () use ($request) {
            $group = ModifierGroup::create($request->groupData());
            $this->syncModifiers($group, $request->validated('modifiers'));

            return $group;
        });

        return redirect()->route('modifier-groups.index')
            ->with('success', __('Modifier group ":name" created.', ['name' => $group->name]));
    }

    public function edit(ModifierGroup $modifierGroup): View
    {
        $modifierGroup->load('modifiers')->loadCount('products');

        return view('modifier-groups.edit', ['group' => $modifierGroup]);
    }

    public function update(ModifierGroupRequest $request, ModifierGroup $modifierGroup): RedirectResponse
    {
        DB::transaction(function () use ($request, $modifierGroup) {
            $modifierGroup->update($request->groupData());
            $this->syncModifiers($modifierGroup, $request->validated('modifiers'));
        });

        return redirect()->route('modifier-groups.index')
            ->with('success', __('Modifier group ":name" updated.', ['name' => $modifierGroup->name]));
    }

    /**
     * Removes the group from all products. Past orders (future module) keep their own snapshot.
     */
    public function destroy(ModifierGroup $modifierGroup): RedirectResponse
    {
        $modifierGroup->delete();

        return redirect()->route('modifier-groups.index')
            ->with('success', __('Modifier group ":name" deleted.', ['name' => $modifierGroup->name]));
    }

    /**
     * Update submitted options in place, create new ones and delete the rest.
     *
     * @param  list<array{id?: int|null, name: string, price: string, is_active: bool}>  $rows
     */
    private function syncModifiers(ModifierGroup $group, array $rows): void
    {
        $keepIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        // Delete removed options first so their names can be reused in the same save.
        Modifier::query()->where('modifier_group_id', $group->getKey())->whereNotIn('id', $keepIds)->delete();

        // Park kept options on temporary names so swapping names (A↔B) cannot hit the unique index mid-save.
        foreach ($keepIds as $id) {
            Modifier::query()->whereKey($id)->where('modifier_group_id', $group->getKey())->update(['name' => "__renaming_{$id}"]);
        }

        foreach (array_values($rows) as $position => $row) {
            $attributes = [
                'name' => $row['name'],
                'price' => $row['price'],
                'is_active' => (bool) ($row['is_active'] ?? false),
                'sort_order' => $position,
            ];

            if (! empty($row['id'])) {
                Modifier::query()->where('modifier_group_id', $group->getKey())->whereKey($row['id'])->firstOrFail()->update($attributes);
            } else {
                $group->modifiers()->create($attributes);
            }
        }
    }
}
