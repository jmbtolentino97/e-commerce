<?php

use App\Models\InventoryMovement;
use App\Models\Product;

test('inventory ledger sums to current stock on hand', function () {
    $product = Product::factory()->create(['track_inventory' => true]);

    InventoryMovement::create([
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 50,
    ]);

    InventoryMovement::create([
        'product_id' => $product->id,
        'type' => 'sale',
        'quantity' => -12,
        'reference_type' => 'order_item',
        'reference_id' => 1,
    ]);

    InventoryMovement::create([
        'product_id' => $product->id,
        'type' => 'return',
        'quantity' => 2,
        'reference_type' => 'order_item',
        'reference_id' => 1,
    ]);

    $sum = InventoryMovement::query()
        ->where('product_id', $product->id)
        ->sum('quantity');

    expect($sum)->toBe(40);

    $row = DB::table('product_current_stock')->where('product_id', $product->id)->first();
    expect((int) $row->stock_on_hand)->toBe(40);
});
