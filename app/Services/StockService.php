<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStockLog;

class StockService
{
    /** Sets stock to an absolute quantity, records a stock log and refreshes the confirmation timestamp. */
    public function set(Product $product, int $quantity, string $reason, ?string $note = null, ?int $userId = null): Product
    {
        $old = (int) $product->stock_quantity;
        $product->stock_quantity = max(0, $quantity);
        $product->stock_confirmed_at = now();
        $product->availability = $this->deriveAvailability($product);
        $product->save();

        if ($old !== $product->stock_quantity || $reason === 'confirmed') {
            ProductStockLog::create([
                'product_id' => $product->id,
                'user_id' => $userId ?? auth()->id(),
                'old_quantity' => $old,
                'new_quantity' => $product->stock_quantity,
                'change' => $product->stock_quantity - $old,
                'reason' => $reason,
                'note' => $note,
            ]);
        }

        return $product;
    }

    public function adjust(Product $product, int $delta, string $reason, ?string $note = null): Product
    {
        $product = Product::whereKey($product->id)->lockForUpdate()->first();

        return $this->set($product, (int) $product->stock_quantity + $delta, $reason, $note);
    }

    private function deriveAvailability(Product $product): string
    {
        if ($product->availability === 'on_order' && $product->stock_quantity === 0) {
            return 'on_order';
        }
        if ($product->stock_quantity === 0) {
            return 'out_of_stock';
        }

        return $product->stock_quantity <= 2 ? 'limited' : 'in_stock';
    }
}
