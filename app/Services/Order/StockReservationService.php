<?php

namespace App\Services\Order;

use App\Models\InventoryMovement;
use App\Models\Order;

class StockReservationService
{
    public function reserve(Order $order): void
    {
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            if (! $item->product || ! $item->product->track_inventory) {
                continue;
            }

            InventoryMovement::create([
                'product_id' => $item->product_id,
                'type' => 'reservation',
                'quantity' => -$item->quantity,
                'reference_type' => 'order_item',
                'reference_id' => $item->id,
                'note' => 'Reservation on place',
            ]);
        }
    }

    public function release(Order $order): void
    {
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            if (! $item->product || ! $item->product->track_inventory) {
                continue;
            }

            InventoryMovement::create([
                'product_id' => $item->product_id,
                'type' => 'release',
                'quantity' => $item->quantity,
                'reference_type' => 'order_item',
                'reference_id' => $item->id,
                'note' => 'Release on cancel',
            ]);
        }
    }

    public function convertReservationToSale(Order $order): void
    {
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            if (! $item->product || ! $item->product->track_inventory) {
                continue;
            }

            // Reverse reservation (+qty), then record sale (-qty) → net -qty
            InventoryMovement::create([
                'product_id' => $item->product_id,
                'type' => 'release',
                'quantity' => $item->quantity,
                'reference_type' => 'order_item',
                'reference_id' => $item->id,
                'note' => 'Release reservation on fulfill',
            ]);

            InventoryMovement::create([
                'product_id' => $item->product_id,
                'type' => 'sale',
                'quantity' => -$item->quantity,
                'reference_type' => 'order_item',
                'reference_id' => $item->id,
                'note' => 'Sale on fulfill',
            ]);
        }
    }
}
