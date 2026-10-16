<?php

namespace App\Http\Controllers;

use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemModifier;
use App\Models\SalePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    /**
     * GET /api/sync/catalog
     * Returns a full catalog snapshot for the desktop to upsert locally.
     */
    public function catalog(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'updated_at']);

        $products = Product::query()
            ->where('is_active', true)
            ->with(['variants', 'modifierGroups.modifiers'])
            ->orderBy('name')
            ->get([
                'id', 'category_id', 'brand_id', 'kitchen_station_id',
                'name', 'sku', 'barcode', 'description', 'image_path',
                'selling_price', 'tax_rate', 'discount_type', 'discount_value',
                'is_active', 'updated_at',
            ]);

        $modifierGroups = ModifierGroup::query()
            ->with('modifiers')
            ->orderBy('name')
            ->get();

        $settings = DB::table('settings')->get(['key', 'value']);

        return response()->json([
            'synced_at'      => now()->toIso8601String(),
            'categories'     => $categories,
            'products'       => $products,
            'modifier_groups' => $modifierGroups,
            'settings'       => $settings->pluck('value', 'key'),
        ]);
    }

    /**
     * POST /api/sync/sales
     * Accepts an array of completed sales from a desktop device.
     * Skips duplicates identified by device_id + invoice_number.
     */
    public function receiveSales(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'device_id'               => ['required', 'string', 'max:20'],
            'sales'                   => ['required', 'array', 'max:200'],
            'sales.*.invoice_number'  => ['required', 'string'],
            'sales.*.status'          => ['required', 'string'],
            'sales.*.completed_at'    => ['required', 'string'],
            'sales.*.business_date'   => ['nullable', 'string'],
            'sales.*.order_no'        => ['nullable', 'integer'],
        ]);

        $deviceId = $payload['device_id'];
        $accepted = [];
        $skipped  = [];

        foreach ($payload['sales'] as $data) {
            $invoice = $data['invoice_number'];

            // Check if already received from this device
            $existing = Sale::query()
                ->where('device_id', $deviceId)
                ->where('invoice_number', $invoice)
                ->first();

            if ($existing) {
                // Phase 1: update if the payload has a newer version (order_no may have changed)
                if (filled($data['business_date'] ?? null) && $existing->order_no === null) {
                    DB::table('sales')->where('id', $existing->id)->update([
                        'business_date' => $data['business_date'],
                        'order_no'      => $data['order_no'] ?? null,
                        'synced_at'     => now(),
                    ]);
                }
                $skipped[] = $invoice;
                continue;
            }

            DB::transaction(function () use ($data, $deviceId, &$accepted, $invoice) {
                $sale = Sale::create([
                    ...$this->saleFields($data),
                    'device_id'     => $deviceId,
                    'business_date' => $data['business_date'] ?? null,
                    'order_no'      => $data['order_no'] ?? null,
                    'synced_at'     => now(),
                ]);

                foreach ($data['items'] ?? [] as $item) {
                    $saleItem = SaleItem::create([
                        'sale_id'    => $sale->id,
                        'product_id' => $item['product_id'] ?? null,
                        'variant_id' => $item['variant_id'] ?? null,
                        'name'       => $item['name'],
                        'unit_price' => $item['unit_price'],
                        'quantity'   => $item['quantity'],
                        'discount'   => $item['discount'] ?? 0,
                        'tax'        => $item['tax'] ?? 0,
                        'total'      => $item['total'],
                        'note'       => $item['note'] ?? null,
                    ]);

                    foreach ($item['modifiers'] ?? [] as $mod) {
                        SaleItemModifier::create([
                            'sale_item_id' => $saleItem->id,
                            'modifier_id'  => $mod['modifier_id'] ?? null,
                            'name'         => $mod['name'],
                            'price'        => $mod['price'] ?? 0,
                        ]);
                    }
                }

                foreach ($data['payments'] ?? [] as $payment) {
                    SalePayment::create([
                        'sale_id'        => $sale->id,
                        'payment_method' => $payment['payment_method'],
                        'amount'         => $payment['amount'],
                    ]);
                }

                $accepted[] = $invoice;
            });
        }

        return response()->json([
            'accepted' => $accepted,
            'skipped'  => $skipped,
        ]);
    }

    private function saleFields(array $d): array
    {
        return [
            'status'               => $d['status'],
            'order_type'           => $d['order_type'] ?? 'takeaway',
            'invoice_number'       => $d['invoice_number'],
            'payment_method'       => $d['payment_method'] ?? null,
            'subtotal'             => $d['subtotal'] ?? 0,
            'item_discount'        => $d['item_discount'] ?? 0,
            'order_discount_type'  => $d['order_discount_type'] ?? null,
            'order_discount_value' => $d['order_discount_value'] ?? 0,
            'order_discount'       => $d['order_discount'] ?? 0,
            'discount'             => $d['discount'] ?? 0,
            'tax'                  => $d['tax'] ?? 0,
            'delivery_fee'         => $d['delivery_fee'] ?? 0,
            'total'                => $d['total'] ?? 0,
            'cash_received'        => $d['cash_received'] ?? 0,
            'change_amount'        => $d['change_amount'] ?? 0,
            'customer_name'        => $d['customer_name'] ?? null,
            'customer_phone'       => $d['customer_phone'] ?? null,
            'customer_address'     => $d['customer_address'] ?? null,
            'notes'                => $d['notes'] ?? null,
            'completed_at'         => $d['completed_at'],
        ];
    }
}
