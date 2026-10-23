<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BarcodeLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:64']]);

        $code = trim($request->string('code'));

        $response = Http::timeout(6)
            ->withHeaders(['User-Agent' => 'SahulatPOS/1.0'])
            ->get("https://world.openfoodfacts.org/api/v2/product/{$code}.json", [
                'fields' => 'product_name,brands,quantity,categories_tags',
            ]);

        if (! $response->successful()) {
            return response()->json(['found' => false]);
        }

        $data = $response->json();

        if (($data['status'] ?? 0) !== 1) {
            return response()->json(['found' => false]);
        }

        $product = $data['product'] ?? [];
        $name = trim($product['product_name'] ?? '');
        $brand = trim(explode(',', $product['brands'] ?? '')[0]);
        $quantity = trim($product['quantity'] ?? '');

        if (blank($name)) {
            return response()->json(['found' => false]);
        }

        $fullName = $name;
        if ($quantity) {
            $fullName .= ' '.$quantity;
        }

        return response()->json([
            'found' => true,
            'name' => $fullName,
            'brand' => $brand ?: null,
        ]);
    }
}
