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
        $this->sections = self::visibleSections(config('navigation.sidebar', []), $request->user(), $request);
    }

    /**
     * Filter navigation by permission and resolve link state for each item.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return list<array{heading: ?string, items: list<array<string, mixed>>}>
     */
    public static function visibleSections(array $sections, ?User $user, Request $request): array
    {
        $resolved = [];

        foreach ($sections as $section) {
            $items = [];

            foreach ($section['items'] ?? [] as $item) {
                $permission = $item['permission'] ?? null;

                if ($permission && ! $user?->can($permission)) {
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
