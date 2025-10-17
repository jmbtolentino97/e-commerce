<?php

namespace App\Services\Order;

use App\Models\Order;

class OrderTotalsService
{
    public function recompute(Order $order): void
    {
        $order->loadMissing('items', 'discountApplications');

        $subtotal = 0.00;
        $lineDiscounts = 0.00;
        $tax = 0.00;

        foreach ($order->items as $item) {
            $subtotal += round($item->unit_price * $item->quantity, 2);
            $lineDiscounts += (float) $item->discount_amount;
            $tax += (float) $item->tax_amount;
        }

        $orderDiscounts = $order->discountApplications()->whereNotNull('order_id')->sum('amount');
        $discountTotal = round($lineDiscounts + $orderDiscounts, 2);

        $grand = round(($subtotal - $discountTotal) + $tax + (float) $order->shipping_total, 2);

        $order->update([
            'subtotal' => round($subtotal, 2),
            'discount_total' => $discountTotal,
            'tax_total' => round($tax, 2),
            'grand_total' => $grand,
        ]);
    }
}
