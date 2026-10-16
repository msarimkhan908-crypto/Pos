<?php

namespace App\Settings;

use App\Support\ImageStorage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Typed, cached access to the `settings` table (bound as a singleton; use the settings()
 * helper). Missing keys fall back to App\Settings\SettingDefinitions defaults.
 *
 *     settings('business.name');            settings()->set(['tax.label' => 'GST']);
 *     settings()->receipt();                settings()->kot();
 */
class Settings
{
    private const CACHE_KEY = 'settings.values';

    /** @var array<string, string|null>|null raw values from the database */
    private ?array $raw = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $definition = SettingDefinitions::get($key);
        $raw = $this->raw();

        if (! array_key_exists($key, $raw)) {
            return $definition['default'] ?? $default;
        }

        return $this->cast($raw[$key], $definition['type'] ?? 'string');
    }

    /**
     * Save several settings at once (unknown keys are ignored).
     *
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        $now = now();

        foreach ($values as $key => $value) {
            $definition = SettingDefinitions::get($key);

            if (! $definition) {
                continue;
            }

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $this->serialise($value, $definition['type']),
                    'type' => $definition['type'],
                    'group' => $definition['group'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $this->flush();
    }

    /**
     * All values of one group, keyed by setting key.
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        return collect(SettingDefinitions::all())
            ->filter(fn (array $definition) => $definition['group'] === $group)
            ->mapWithKeys(fn (array $definition, string $key) => [$key => $this->get($key)])
            ->all();
    }

    /**
     * Customer receipt layout, merged over the defaults.
     *
     * @return array<string, mixed>
     */
    public function receipt(): array
    {
        return [...SettingDefinitions::receiptDefaults(), ...(array) $this->get('receipt.customer')];
    }

    /**
     * Kitchen receipt (KOT) layout, merged over the defaults.
     *
     * @return array<string, mixed>
     */
    public function kot(): array
    {
        return [...SettingDefinitions::kotDefaults(), ...(array) $this->get('receipt.kitchen')];
    }

    /**
     * Business details printed on receipts and shown in the app header.
     *
     * @return array<string, mixed>
     */
    public function business(): array
    {
        $values = collect($this->group('business'))->mapWithKeys(fn ($value, string $key) => [substr($key, 9) => $value])->all();

        return [
            ...$values,
            'name' => $values['name'] ?: config('app.name', 'POS'),
            'logo_url' => ImageStorage::url($values['logo']),
        ];
    }

    public function flush(): void
    {
        $this->raw = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string|null>
     */
    private function raw(): array
    {
        if ($this->raw !== null) {
            return $this->raw;
        }

        try {
            return $this->raw = Cache::rememberForever(
                self::CACHE_KEY,
                fn () => DB::table('settings')->pluck('value', 'key')->all(),
            );
        } catch (Throwable) {
            // Before the settings table is migrated (fresh install, first test boot): use defaults.
            return $this->raw = [];
        }
    }

    private function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => in_array($value, ['1', 'true'], true),
            'json' => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    private function serialise(mixed $value, string $type): ?string
    {
        if ($value === null || $value === '') {
            return $type === 'boolean' ? '0' : null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
