<?php

namespace App\Console\Commands;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SyncPush extends Command
{
    protected $signature   = 'sync:push';
    protected $description = 'Push unsynced local sales to the sync server';

    public function handle(): int
    {
        $serverUrl = config('app.sync_server_url');
        $token     = config('app.sync_token');
        $deviceId  = config('app.device_id');

        if (blank($serverUrl) || blank($token) || blank($deviceId)) {
            $this->line('Sync not configured — skipping push.');
            return self::SUCCESS;
        }

        $pending = Sale::completed()
            ->whereNull('synced_at')
            ->with(['items.modifiers', 'payments'])
            ->orderBy('id')
            ->take(100)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Nothing to push.');
            return self::SUCCESS;
        }

        $this->info("Pushing {$pending->count()} sale(s)…");

        $payload = $pending->map(fn (Sale $sale) => $this->serializeSale($sale))->values()->all();

        try {
            $response = Http::withToken($token)
                ->timeout(60)
                ->post(rtrim($serverUrl, '/').'/api/sync/sales', [
                    'device_id' => $deviceId,
                    'sales'     => $payload,
                ]);

            if (! $response->successful()) {
                $this->error("Server returned {$response->status()}.");
                return self::FAILURE;
            }

            $accepted = $response->json('accepted', []);

            if (! empty($accepted)) {
                DB::table('sales')
                    ->whereIn('invoice_number', $accepted)
                    ->update(['synced_at' => now()]);

                $this->info('Pushed: '.implode(', ', $accepted));
            }

            if (! empty($response->json('skipped', []))) {
                $this->line('Already on server: '.implode(', ', $response->json('skipped')));
                // Mark server-acknowledged duplicates as synced too
                DB::table('sales')
                    ->whereIn('invoice_number', $response->json('skipped'))
                    ->update(['synced_at' => now()]);
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Push failed: '.$e->getMessage());
            return self::FAILURE;
        }
    }

    private function serializeSale(Sale $sale): array
    {
        return [
            'invoice_number'       => $sale->invoice_number,
            'business_date'        => $sale->business_date?->toDateString(),
            'order_no'             => $sale->order_no,
            'status'               => $sale->status->value,
            'order_type'           => $sale->order_type?->value,
            'payment_method'       => $sale->payment_method?->value,
            'subtotal'             => (string) $sale->subtotal,
            'item_discount'        => (string) $sale->item_discount,
            'order_discount_type'  => $sale->order_discount_type?->value,
            'order_discount_value' => (string) $sale->order_discount_value,
            'order_discount'       => (string) $sale->order_discount,
            'discount'             => (string) $sale->discount,
            'tax'                  => (string) $sale->tax,
            'delivery_fee'         => (string) $sale->delivery_fee,
            'total'                => (string) $sale->total,
            'cash_received'        => (string) $sale->cash_received,
            'change_amount'        => (string) $sale->change_amount,
            'customer_name'        => $sale->customer_name,
            'customer_phone'       => $sale->customer_phone,
            'customer_address'     => $sale->customer_address,
            'notes'                => $sale->notes,
            'completed_at'         => $sale->completed_at?->toIso8601String(),
            'items'                => $sale->items->map(fn ($item) => [
                'product_id'  => $item->product_id,
                'variant_id'  => $item->variant_id,
                'name'        => $item->name,
                'unit_price'  => (string) $item->unit_price,
                'quantity'    => $item->quantity,
                'discount'    => (string) $item->discount,
                'tax'         => (string) $item->tax,
                'total'       => (string) $item->total,
                'note'        => $item->note,
                'modifiers'   => $item->modifiers->map(fn ($m) => [
                    'modifier_id' => $m->modifier_id,
                    'name'        => $m->name,
                    'price'       => (string) $m->price,
                ])->all(),
            ])->all(),
            'payments' => $sale->payments->map(fn ($p) => [
                'payment_method' => $p->payment_method?->value,
                'amount'         => (string) $p->amount,
            ])->all(),
        ];
    }
}
