<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Discount;
use App\Models\DiscountApplication;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::query()->inRandomOrder()->get();
        $products = Product::query()->where('is_active', true)->get();
        $discounts = Discount::query()->active()->get();

        // Create ~50 orders spread across customers
        foreach (range(1, 50) as $_) {
            DB::transaction(function () use ($customers, $products, $discounts) {
                $customer = $customers->random();

                /** @var Order $order */
                $order = Order::factory()->create([
                    'customer_id' => $customer->id,
                    'status' => 'paid',
                    'placed_at' => now()->subDays(random_int(0, 45)),
                    'paid_at' => now()->subDays(random_int(0, 45)),
                    'shipping_total' => round(mt_rand(0, 1) ? 0 : (mt_rand(500, 1500) / 100), 2),
                ]);

                // 1–5 items
                $itemsCount = random_int(1, 5);
                $chosen = $products->random($itemsCount);

                $subtotal = 0;
                $discountTotal = 0;
                $taxTotal = 0;

                foreach ($chosen as $product) {
                    $qty = random_int(1, 3);
                    $unit = $product->price;
                    $lineDisc = round(mt_rand(0, 1) ? ($unit * $qty * 0.1) : 0, 2);
                    $lineTax = round(($unit * $qty - $lineDisc) * 0.12, 2); // 12% sample VAT
                    $lineTotal = round(($unit * $qty) - $lineDisc + $lineTax, 2);

                    /** @var OrderItem $item */
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

                    // Inventory: decrement on sale
                    if ($product->track_inventory) {
                        InventoryMovement::create([
                            'product_id' => $product->id,
                            'type' => 'sale',
                            'quantity' => -$qty,
                            'reference_type' => 'order_item',
                            'reference_id' => $item->id,
                            'note' => 'Order item sale',
                            'created_by' => null,
                        ]);
                    }

                    $subtotal += round($unit * $qty, 2);
                    $discountTotal += $lineDisc;
                    $taxTotal += $lineTax;

                    // Random item-level discount application record
                    if ($lineDisc > 0) {
                        $picked = $discounts->where('target', 'item')->random(fn () => 1, fn () => 0);
                        if ($picked) {
                            DiscountApplication::create([
                                'discount_id' => $picked->first()->id,
                                'order_id' => null,
                                'order_item_id' => $item->id,
                                'amount' => $lineDisc,
                            ]);
                        }
                    }
                }

                // Order-level discount
                $orderLevelDisc = 0;
                if ($discounts->where('target', 'order')->isNotEmpty() && mt_rand(0, 1)) {
                    $disc = $discounts->where('target', 'order')->random();
                    if ($disc->type === 'percentage') {
                        $orderLevelDisc = round($subtotal * ($disc->value / 100), 2);
                    } elseif ($disc->type === 'fixed') {
                        $orderLevelDisc = (float) min($disc->value, $subtotal);
                    } elseif ($disc->type === 'free_shipping') {
                        // handled by keeping shipping_total at 0 when code is applied
                        if ($order->shipping_total > 0) {
                            $orderLevelDisc = min($order->shipping_total, $order->shipping_total);
                            $order->shipping_total = 0.00;
                        }
                    }

                    if ($orderLevelDisc > 0) {
                        DiscountApplication::create([
                            'discount_id' => $disc->id,
                            'order_id' => $order->id,
                            'order_item_id' => null,
                            'amount' => $orderLevelDisc,
                        ]);
                    }
                }

                $discountTotal = round($discountTotal + $orderLevelDisc, 2);

                $order->update([
                    'subtotal' => round($subtotal, 2),
                    'discount_total' => $discountTotal,
                    'tax_total' => round($taxTotal, 2),
                    'grand_total' => round(($subtotal - $discountTotal) + $taxTotal + $order->shipping_total, 2),
                ]);
            });
        }
    }
}
