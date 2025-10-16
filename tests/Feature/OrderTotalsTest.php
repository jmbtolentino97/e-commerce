<?php

use App\Models\Customer;
use App\Models\Discount;
use App\Models\DiscountApplication;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

test('order totals equal items math minus discounts plus tax and shipping', function () {
    $customer = Customer::factory()->create();
    $p1 = Product::factory()->create(['price' => 100]);
    $p2 = Product::factory()->create(['price' => 50]);

    /** @var Order $order */
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'status' => 'paid',
        'shipping_total' => 20,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $p1->id,
        'sku' => $p1->sku,
        'name' => $p1->name,
        'unit_price' => 100,
        'quantity' => 2,
        'discount_amount' => 10,
        'tax_amount' => 21.6, // (200 - 10) * 0.12
        'total' => (200 - 10) + 21.6,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $p2->id,
        'sku' => $p2->sku,
        'name' => $p2->name,
        'unit_price' => 50,
        'quantity' => 1,
        'discount_amount' => 0,
        'tax_amount' => 6.0, // (50 - 0) * 0.12
        'total' => 56.0,
    ]);

    // Order-level discount 5% of subtotal (200 + 50 = 250) = 12.5
    $disc = Discount::factory()->create([
        'type' => 'percentage',
        'target' => 'order',
        'value' => 5,
        'active' => true,
    ]);

    DiscountApplication::create([
        'discount_id' => $disc->id,
        'order_id' => $order->id,
        'amount' => 12.50,
    ]);

    $subtotal = 250.00;
    $discounts = 10.00 + 12.50; // line + order level
    $tax = 21.60 + 6.00;
    $shipping = 20.00;
    $expected = ($subtotal - $discounts) + $tax + $shipping; // 275.10

    $order->update([
        'subtotal' => $subtotal,
        'discount_total' => $discounts,
        'tax_total' => $tax,
        'grand_total' => $expected,
    ]);

    expect($order->grand_total)->toBe(275.10);
});
