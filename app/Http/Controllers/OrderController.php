<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class OrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:sales.view'),
        ];
    }

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->limit(100)->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $payment = $request->string('payment')->toString();

        $orders = Sale::query()
            ->completed()
            ->withCount('items')
            ->with(['customer:id,name', 'user:id,name', 'table:id,name'])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('order_no', is_numeric($search) ? (int) $search : -1)
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            }))
            ->when($from, fn ($q) => $q->whereDate('completed_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('completed_at', '<=', $to))
            ->when($payment, fn ($q) => $q->where('payment_method', $payment))
            ->latest('completed_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $paymentOptions = collect(PaymentMethod::cases())
            ->mapWithKeys(fn ($m) => [$m->value => $m->label()])
            ->all();

        return view('orders.index', compact('orders', 'search', 'from', 'to', 'payment', 'paymentOptions'));
    }

    public function show(Sale $order): View
    {
        abort_unless($order->isCompleted() || $order->isCancelled(), 404);

        $order->load([
            'items.modifiers',
            'payments',
            'customer',
            'user',
            'table',
            'returns.items',
            'returns.processor:id,name',
            'kitchenOrders.items',
        ]);

        return view('orders.show', compact('order'));
    }
}
