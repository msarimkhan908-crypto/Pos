<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Modifier;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SyncPull extends Command
{
    protected $signature   = 'sync:pull';
    protected $description = 'Pull catalog and settings from the sync server';

    public function handle(): int
    {
        $serverUrl = config('app.sync_server_url');
        $token     = config('app.sync_token');

        if (blank($serverUrl) || blank($token)) {
            $this->line('Sync not configured — skipping pull.');
            return self::SUCCESS;
        }

        $this->info('Pulling catalog from server…');

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->get(rtrim($serverUrl, '/').'/api/sync/catalog');

            if (! $response->successful()) {
                $this->error("Server returned {$response->status()}.");
                return self::FAILURE;
            }

            $data = $response->json();

            DB::transaction(function () use ($data) {
                $this->upsertCategories($data['categories'] ?? []);
                $this->upsertModifierGroups($data['modifier_groups'] ?? []);
                $this->upsertProducts($data['products'] ?? []);
                $this->upsertSettings($data['settings'] ?? []);
            });

            // Write last-sync timestamp
            $status = ['synced_at' => now()->toIso8601String(), 'status' => 'ok'];
            file_put_contents(storage_path('app/last_sync.json'), json_encode($status));

            $this->info('Catalog synced successfully.');
            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Pull failed: '.$e->getMessage());
            return self::FAILURE;
        }
    }

    private function upsertCategories(array $rows): void
    {
        foreach ($rows as $row) {
            Category::withoutGlobalScopes()->updateOrCreate(
                ['id' => $row['id']],
                ['name' => $row['name'], 'is_active' => $row['is_active'] ?? true]
            );
        }
    }

    private function upsertModifierGroups(array $groups): void
    {
        foreach ($groups as $group) {
            $mg = ModifierGroup::updateOrCreate(
                ['id' => $group['id']],
                ['name' => $group['name'], 'required' => $group['required'] ?? false, 'multiple' => $group['multiple'] ?? false]
            );

            foreach ($group['modifiers'] ?? [] as $mod) {
                Modifier::updateOrCreate(
                    ['id' => $mod['id']],
                    ['modifier_group_id' => $mg->id, 'name' => $mod['name'], 'price' => $mod['price'] ?? 0, 'is_active' => $mod['is_active'] ?? true]
                );
            }
        }
    }

    private function upsertProducts(array $rows): void
    {
        foreach ($rows as $row) {
            $product = Product::withTrashed()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'category_id'      => $row['category_id'],
                    'brand_id'         => $row['brand_id'] ?? null,
                    'kitchen_station_id' => $row['kitchen_station_id'] ?? null,
                    'name'             => $row['name'],
                    'sku'              => $row['sku'],
                    'barcode'          => $row['barcode'] ?? null,
                    'description'      => $row['description'] ?? null,
                    'selling_price'    => $row['selling_price'],
                    'tax_rate'         => $row['tax_rate'] ?? 0,
                    'discount_type'    => $row['discount_type'] ?? null,
                    'discount_value'   => $row['discount_value'] ?? 0,
                    'is_active'        => $row['is_active'] ?? true,
                    'deleted_at'       => null,
                ]
            );

            // Sync modifier group pivot
            if (isset($row['modifier_groups'])) {
                $product->modifierGroups()->sync(
                    collect($row['modifier_groups'])->pluck('id')->toArray()
                );
            }
        }
    }

    private function upsertSettings(array $settings): void
    {
        $skipKeys = ['general.device_id', 'app.sync_token', 'app.sync_server_url'];

        foreach ($settings as $key => $value) {
            if (in_array($key, $skipKeys, true)) {
                continue;
            }
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
        }
    }
}
