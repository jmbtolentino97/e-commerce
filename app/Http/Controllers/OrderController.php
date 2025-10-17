<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\AddOrderItemRequest;
use App\Http\Requests\Order\ApplyDiscountRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderItemRequest;
use App\Models\Discount;
use App\Models\DiscountApplication;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Order\OrderTotalsService;
use App\Services\Order\StockReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Enums\FilterOperator;
use Spatie\QueryBuilder\QueryBuilder;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = QueryBuilder::for(Order::query())
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::operator('min_created_at', FilterOperator::GREATER_THAN_OR_EQUAL, 'and', 'created_at'),
                AllowedFilter::operator('max_created_at', FilterOperator::LESS_THAN_OR_EQUAL, 'and', 'created_at'),
                AllowedFilter::operator('min_total', FilterOperator::GREATER_THAN_OR_EQUAL, 'and', 'grand_total'),
                AllowedFilter::operator('max_total', FilterOperator::LESS_THAN_OR_EQUAL, 'and', 'grand_total'),
            ])
            ->allowedSorts(['created_at', 'grand_total'])
            ->allowedIncludes(['customer', 'items.product', 'discountApplications'])
            ->paginate($request->input('page_size', 15));

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Order $order)
    {
        $order->load(request('include') ? explode(',', request('include')) : []);

        return response()->json(['data' => $order]);
    }

    public function store(StoreOrderRequest $request, OrderTotalsService $totals)
    {
        return DB::transaction(function () use ($request, $totals) {
            $order = Order::create([
                'order_number' => 'SO-' . now()->format('Y') . '-' . str_pad((string) (Order::max('id') + 1), 6, '0', STR_PAD_LEFT),
                'customer_id' => $request->integer('customer_id'),
                'status' => 'draft',
                'currency' => $request->input('currency', 'USD'),
                'shipping_total' => $request->input('shipping_total', 0),
                'notes' => $request->input('notes'),
            ]);

            $totals->recompute($order);

            return response()->json(['data' => $order->fresh('items')], 201);
        });
    }

    public function addItem(Order $order, AddOrderItemRequest $request, OrderTotalsService $totals)
    {
        $this->ensureDraft($order);

        $product = Product::findOrFail($request->integer('product_id'));
        $qty = $request->integer('quantity');

        $unit = $request->has('unit_price') ? (float) $request->input('unit_price') : (float) $product->price;
        $lineDisc = (float) $request->input('discount_amount', 0);
        $taxRate = (float) $request->input('tax_rate', 0);
        $lineTax = round(($unit * $qty - $lineDisc) * $taxRate, 2);
        $lineTotal = round(($unit * $qty) - $lineDisc + $lineTax, 2);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit_price' => $unit,
            'quantity' => $qty,
            'discount_amount' => $lineDisc,
            'tax_amount' => $lineTax,
            'total' => $lineTotal,
        ]);

        $totals->recompute($order);

        return response()->json(['data' => $item], 201);
    }

    public function updateItem(Order $order, OrderItem $item, UpdateOrderItemRequest $request, OrderTotalsService $totals)
    {
        $this->ensureDraft($order);
        $this->assertItemBelongsToOrder($order, $item);

        $payload = $request->validated();
        $qty = $payload['quantity'] ?? $item->quantity;
        $unit = isset($payload['unit_price']) ? (float) $payload['unit_price'] : (float) $item->unit_price;
        $disc = isset($payload['discount_amount']) ? (float) $payload['discount_amount'] : (float) $item->discount_amount;
        $taxRate = isset($payload['tax_rate']) ? (float) $payload['tax_rate'] : 0;
        $tax = round(($unit * $qty - $disc) * $taxRate, 2);

        $item->update(array_merge($payload, [
            'tax_amount' => $tax,
            'total' => round(($unit * $qty) - $disc + $tax, 2),
        ]));

        $totals->recompute($order);

        return response()->json(['data' => $item->fresh()]);
    }

    public function removeItem(Order $order, OrderItem $item, OrderTotalsService $totals)
    {
        $this->ensureDraft($order);
        $this->assertItemBelongsToOrder($order, $item);

        $item->delete();

        $totals->recompute($order);

        return response()->json(['message' => 'Item removed.']);
    }

    public function applyDiscount(Order $order, ApplyDiscountRequest $request, OrderTotalsService $totals)
    {
        $this->ensureDraft($order);

        $discount = Discount::query()->active()->where('code', $request->string('code'))->first();
        if (! $discount) {
            return response()->json(['message' => 'Discount code not found or inactive.'], 422);
        }

        // Scope handling
        $scope = $request->input('scope', 'order');
        if ($discount->target === 'item') {
            $scope = 'item';
        }

        if ($scope === 'item') {
            $itemId = $request->integer('order_item_id');
            $item = OrderItem::query()->where('order_id', $order->id)->where('id', $itemId)->first();
            if (! $item) {
                return response()->json(['message' => 'Order item not found for this order.'], 422);
            }
            $amount = $this->computeDiscountAmount($discount, $item->unit_price * $item->quantity);
            DiscountApplication::create([
                'discount_id' => $discount->id,
                'order_item_id' => $item->id,
                'amount' => $amount,
            ]);

            // reflect on line snapshot immediately (optional)
            $item->update([
                'discount_amount' => round($item->discount_amount + $amount, 2),
                'total' => round(($item->unit_price * $item->quantity) - ($item->discount_amount + $amount) + $item->tax_amount, 2),
            ]);
        } else {
            // order scope
            $amount = $this->computeDiscountAmount($discount, (float) $order->subtotal + (float) $order->shipping_total);
            DiscountApplication::create([
                'discount_id' => $discount->id,
                'order_id' => $order->id,
                'amount' => $amount,
            ]);
            if ($discount->type === 'free_shipping') {
                $order->update(['shipping_total' => 0.00]);
            }
        }

        $totals->recompute($order->fresh('items'));

        return response()->json(['data' => $order->fresh(['items', 'discountApplications'])]);
    }

    public function removeDiscounts(Order $order, OrderTotalsService $totals)
    {
        $this->ensureDraft($order);

        DiscountApplication::where('order_id', $order->id)->orWhereIn('order_item_id', $order->items()->pluck('id'))->delete();

        $totals->recompute($order->fresh('items'));

        return response()->json(['message' => 'Discounts removed.']);
    }

    public function place(Order $order, StockReservationService $stock, OrderTotalsService $totals)
    {
        $this->ensureDraft($order);

        // Recompute before reserving
        $totals->recompute($order->fresh('items'));

        DB::transaction(function () use ($order, $stock) {
            $stock->reserve($order);
            $order->update([
                'status' => 'pending_payment',
                'placed_at' => now(),
            ]);
        });

        return response()->json(['data' => $order->fresh('items')]);
    }

    public function pay(Order $order)
    {
        if (! in_array($order->status, ['pending_payment', 'draft'])) {
            return response()->json(['message' => 'Order cannot be paid in current status.'], 422);
        }

        $order->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return response()->json(['data' => $order]);
    }

    public function fulfill(Order $order, StockReservationService $stock)
    {
        if (! in_array($order->status, ['paid'])) {
            return response()->json(['message' => 'Order cannot be fulfilled in current status.'], 422);
        }

        DB::transaction(function () use ($order, $stock) {
            $stock->convertReservationToSale($order);
            $order->update(['status' => 'fulfilled']);
        });

        return response()->json(['data' => $order->fresh()]);
    }

    public function cancel(Order $order, StockReservationService $stock)
    {
        if (! in_array($order->status, ['draft', 'pending_payment', 'paid'])) {
            return response()->json(['message' => 'Order cannot be cancelled in current status.'], 422);
        }

        DB::transaction(function () use ($order, $stock) {
            $stock->release($order);
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        });

        return response()->json(['data' => $order->fresh()]);
    }

    // --- helpers ---

    protected function ensureDraft(Order $order): void
    {
        if ($order->status !== 'draft') {
            abort(response()->json(['message' => 'Only draft orders can be modified.'], 422));
        }
    }

    protected function assertItemBelongsToOrder(Order $order, OrderItem $item): void
    {
        if ($item->order_id !== $order->id) {
            abort(404);
        }
    }

    protected function computeDiscountAmount(Discount $discount, float $base): float
    {
        return match ($discount->type) {
            'percentage' => round($base * ($discount->value / 100), 2),
            'fixed' => (float) min($discount->value, $base),
            'free_shipping' => 0.00,
            default => 0.00,
        };
    }
}
