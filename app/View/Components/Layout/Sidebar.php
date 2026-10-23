<?php

namespace App\View\Components\Layout;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Illuminate\View\View;

class Sidebar extends Component
{
    /**
     * Sidebar sections visible to the current user.
     *
     * @var list<array{heading: ?string, items: list<array<string, mixed>>}>
     */
    public array $sections;

    public function __construct(Request $request)
    {
        $org = $request->user()?->organization;
        $this->sections = self::visibleSections(config('navigation.sidebar', []), $request->user(), $request, $org?->isRestaurant() ?? false);
    }

    /**
     * Filter navigation by permission and business type, then resolve link state.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return list<array{heading: ?string, items: list<array<string, mixed>>}>
     */
    public static function visibleSections(array $sections, ?User $user, Request $request, bool $isRestaurant = false): array
    {
        $resolved = [];

        foreach ($sections as $section) {
            // Hide the whole Restaurant section for non-restaurant businesses
            if (($section['restaurant_only'] ?? false) && ! $isRestaurant) {
                continue;
            }

            $items = [];

            foreach ($section['items'] ?? [] as $item) {
                $permission = $item['permission'] ?? null;

                if ($permission && ! $user?->can($permission)) {
                    continue;
                }

                // Hide restaurant-only items for non-restaurant businesses
                if (($item['restaurant_only'] ?? false) && ! $isRestaurant) {
                    continue;
                }

                $available = Route::has($item['route']);

                $items[] = [
                    'label' => __($item['label']),
                    'icon' => $item['icon'] ?? null,
                    'href' => $available ? route($item['route']) : null,
                    'active' => $available && $request->routeIs(...(array) ($item['active'] ?? $item['route'])),
                    'available' => $available,
                ];
            }

            if ($items !== []) {
                $resolved[] = [
                    'heading' => isset($section['heading']) ? __($section['heading']) : null,
                    'items' => $items,
                ];
            }
        }

        return $resolved;
    }

    public function render(): View
    {
        return view('components.layout.sidebar');
    }
}
