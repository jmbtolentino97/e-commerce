<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class DiscountApplicationService
{
    public function ensureCanApply(Discount $discount, Order $order, ?OrderItem $item = null): void
    {
        // 1) Active window & flag already guarded by scope 'active'
        // 2) Target scope
        if ($discount->target === 'item' && ! $item) {
            abort(response()->json(['message' => 'Discount requires item scope.'], 422));
        }

        // 3) Min order amount (based on current order subtotal + shipping)
        if ($discount->min_order_amount !== null) {
            $orderBase = (float) $order->subtotal + (float) $order->shipping_total;
            if ($orderBase < (float) $discount->min_order_amount) {
                abort(response()->json(['message' => 'Minimum order amount not met.'], 422));
            }
        }

        // 4) Stackability rules:
        if (! $discount->stackable) {
            $hasExisting = $discount->target === 'order'
                ? $order->discountApplications()->exists()
                : $order->items()->whereHas('discountApplications')->exists();

            if ($hasExisting) {
                abort(response()->json(['message' => 'Non-stackable discount cannot be combined with others.'], 422));
            }
        }

        // 5) Usage limit (global)
        if ($discount->usage_limit !== null) {
            $used = DB::table('discount_applications')->where('discount_id', $discount->id)->count();
            if ($used >= (int) $discount->usage_limit) {
                abort(response()->json(['message' => 'Discount usage limit reached.'], 422));
            }
        }

        // 6) Per-customer limit
        if ($discount->per_customer_limit !== null) {
            $customerId = $order->customer_id;
            $usedByCustomer = DB::table('discount_applications as da')
                ->leftJoin('orders as o', 'da.order_id', '=', 'o.id')
                ->leftJoin('order_items as oi', 'da.order_item_id', '=', 'oi.id')
                ->leftJoin('orders as o2', 'oi.order_id', '=', 'o2.id')
                ->where('da.discount_id', $discount->id)
                ->where(function ($q) use ($customerId) {
                    $q->where('o.customer_id', $customerId)
                      ->orWhere('o2.customer_id', $customerId);
                })
                ->count();

            if ($usedByCustomer >= (int) $discount->per_customer_limit) {
                abort(response()->json(['message' => 'Per-customer redemption limit reached.'], 422));
            }
        }
    }
}
