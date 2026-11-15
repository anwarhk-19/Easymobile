<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductModerationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Product::with(['shop:id,name', 'category:id,name', 'brand:id,name', 'primaryImage'])->latest();
        if ($request->filled('q')) {
            $like = '%'.$request->input('q').'%';
            $q->where(fn ($s) => $s->where('name', 'like', $like)->orWhere('sku', 'like', $like));
        }
        if ($request->filled('shop_id')) {
            $q->where('shop_id', $request->integer('shop_id'));
        }
        if ($request->filled('status')) {
            $request->input('status') === 'suspended' ? $q->where('is_suspended', true) : $q->where('status', $request->input('status'));
        }

        return response()->json($q->paginate(25)->withQueryString());
    }

    public function update(Request $request, Product $product, Notifier $notifier): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:suspend,reinstate'], 'reason' => ['required_if:action,suspend', 'nullable', 'string', 'max:500']]);
        $suspend = $data['action'] === 'suspend';
        $product->forceFill(['is_suspended' => $suspend, 'suspension_reason' => $suspend ? $data['reason'] : null])->save();
        AuditLogger::log('product.'.$data['action'], $product, ucfirst($data['action'])." listing {$product->name}", ['reason' => $data['reason'] ?? null]);
        $notifier->notify($product->shop->merchant, 'Listing '.($suspend ? 'suspended' : 'reinstated'), 'shop_status',
            ['shop_name' => $product->name, 'status_label' => $suspend ? 'suspended: '.$data['reason'] : 'visible again'], '/merchant/products');

        return response()->json(['product' => $product]);
    }
}
