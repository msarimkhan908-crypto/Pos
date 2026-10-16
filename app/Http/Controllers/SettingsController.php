<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingsRequest;
use App\Settings\SettingDefinitions;
use Illuminate\Validation\Rule;
use App\Support\Printing;
use App\Support\ReceiptSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Settings pages. Viewing needs settings.view; saving needs settings.manage (checked again
 * in SettingsRequest). Values are stored by App\Settings\Settings.
 */
class SettingsController extends Controller implements HasMiddleware
{
    /**
     * Section => [sidebar page, tab title]. Sections on the same page are shown as tabs.
     */
    public const SECTIONS = [
        'business' => ['general', 'Business'],
        'general' => ['general', 'General'],
        'appearance' => ['general', 'Appearance'],
        'dashboard' => ['general', 'Dashboard'],
        'pos' => ['pos', 'Printing'],
        'receipt' => ['receipt', 'Customer receipt'],
        'kot' => ['receipt', 'Kitchen receipt (KOT)'],
        'tax' => ['tax', 'Tax'],
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:settings.view', only: ['show', 'preview']),
            new Middleware('permission:settings.manage', only: ['update']),
        ];
    }

    public function show(string $section): View
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        $page = self::SECTIONS[$section][0];

        return view('settings.show', [
            'section' => $section,
            'tabs' => collect(self::SECTIONS)->filter(fn (array $s) => $s[0] === $page)->map(fn (array $s) => __($s[1])),
            'readOnly' => ! auth()->user()->can('settings.manage'),
        ]);
    }

    public function update(SettingsRequest $request, string $section): RedirectResponse
    {
        settings()->set($request->settingsValues());

        return redirect()->route("settings.{$section}")->with('success', __(':section settings saved.', ['section' => __(self::SECTIONS[$section][1])]));
    }

    /**
     * Preview uses plain Request (no FormRequest) so file-upload validation
     * can never generate a 422. Auth is checked manually.
     */
    public function preview(Request $request, string $kind): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('settings.view'), 403);

        // Validate width and paper dimensions up front so callers get a proper 422 on bad input.
        $widthRules = $kind === 'kot'
            ? ['width' => ['required', Rule::in(SettingDefinitions::KOT_WIDTHS)]]
            : ['width' => ['required', Rule::in(SettingDefinitions::RECEIPT_WIDTHS)]];
        $request->validate($widthRules);

        try {
            if ($kind === 'kot') {
                $config = $this->previewConfig($request, SettingDefinitions::kotDefaults());
                $order = ReceiptSample::kitchenOrder();
                $html = view('prints.partials.kot', [
                    'order' => $order,
                    'groups' => $order->itemsByStation(),
                    'sample' => true,
                    ...Printing::kot($config),
                ])->render();

                return response()->json(['html' => $html]);
            }

            $config = $this->previewConfig($request, SettingDefinitions::receiptDefaults());

            // Embed an uploaded preview image as base64 so it renders without saving to disk.
            $image = null;
            if ($request->hasFile('header_image') && $request->file('header_image')->isValid()) {
                $file = $request->file('header_image');
                $mime = $file->getMimeType() ?? 'image/jpeg';
                $image = 'data:' . $mime . ';base64,' . base64_encode((string) $file->get());
            } elseif ($request->boolean('remove_header_image')) {
                $image = settings()->business()['logo_url'];
            }

            $html = view('prints.partials.receipt', [
                'sale' => ReceiptSample::sale(),
                'sample' => true,
                ...Printing::receipt($config, $image),
            ])->render();

            return response()->json(['html' => $html]);
        } catch (\Throwable $e) {
            \Log::error('[preview] exception', ['msg' => $e->getMessage()]);
            $html = view('prints.partials.receipt', [
                'sale' => ReceiptSample::sale(),
                'sample' => true,
                ...Printing::receipt(),
            ])->render();
            return response()->json(['html' => $html]);
        }
    }

    private function previewConfig(Request $request, array $defaults): array
    {
        return collect($defaults)->map(function ($default, string $key) use ($request) {
            return is_bool($default)
                ? $request->boolean($key)
                : (string) ($request->input($key) ?? '');
        })->all();
    }
}
