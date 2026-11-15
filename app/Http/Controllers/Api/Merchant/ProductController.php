<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Services\FileStore;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends MerchantController
{
    public function __construct(private StockService $stock, private FileStore $files) {}

    public function index(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $q = Product::where('shop_id', $shop->id)->with(['primaryImage', 'category:id,name', 'brand:id,name'])->latest('updated_at');
        if ($request->filled('q')) {
            $like = '%'.$request->input('q').'%';
            $q->where(fn ($s) => $s->where('name', 'like', $like)->orWhere('sku', 'like', $like));
        }
        if ($request->filled('status')) {
            match ($request->input('status')) {
                'out_of_stock' => $q->where('availability', 'out_of_stock'),
                'suspended' => $q->where('is_suspended', true),
                'stale' => $q->where(fn ($s) => $s->whereNull('stock_confirmed_at')->orWhere('stock_confirmed_at', '<', now()->subDays((int) \App\Models\Setting::get('stock_stale_days')))),
                default => $q->where('status', $request->input('status')),
            };
        }
        if ($request->filled('category_id')) {
            $q->where('category_id', $request->integer('category_id'));
        }

        return response()->json($q->paginate(25)->withQueryString());
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('manage', $product);

        return response()->json(['product' => $product->load(['images', 'compatibleModels.brand:id,name', 'category:id,name', 'brand:id,name'])]);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $request->validated();
        $product = DB::transaction(function () use ($shop, $data, $request) {
            $product = Product::create(Arr::except($data, ['compatible_model_ids', 'images', 'stock_quantity']) + [
                'shop_id' => $shop->id,
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
                'status' => $data['status'] ?? 'active',
                'stock_quantity' => 0,
            ]);
            $product->compatibleModels()->sync($data['compatible_model_ids'] ?? []);
            $this->stock->set($product, (int) $data['stock_quantity'], 'initial');
            $product->update(['availability' => $data['availability'] === 'on_order' && (int) $data['stock_quantity'] === 0 ? 'on_order' : $product->availability]);
            $this->storeImages($product, $request);

            return $product;
        });

        return response()->json(['product' => $product->load('images', 'compatibleModels')], 201);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('manage', $product);
        $data = $request->validated();
        DB::transaction(function () use ($product, $data, $request) {
            $product->update(Arr::except($data, ['compatible_model_ids', 'images', 'stock_quantity', 'availability']));
            $product->compatibleModels()->sync($data['compatible_model_ids'] ?? []);
            if ((int) $data['stock_quantity'] !== (int) $product->stock_quantity) {
                $this->stock->set($product, (int) $data['stock_quantity'], 'manual_update');
            }
            // Explicit availability overrides such as "on order" are respected when stock is zero.
            if ($data['availability'] === 'on_order' || $data['availability'] === 'out_of_stock') {
                $product->update(['availability' => $product->stock_quantity === 0 ? $data['availability'] : $product->availability]);
            }
            $this->storeImages($product, $request);
        });

        return response()->json(['product' => $product->fresh(['images', 'compatibleModels'])]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('manage', $product);
        $product->update(['status' => 'inactive']);
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }

    public function toggle(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('manage', $product);
        $product->update(['status' => $product->status === 'active' ? 'inactive' : 'active']);

        return response()->json(['product' => $product]);
    }

    public function updateStock(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('manage', $product);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'reason' => ['required', Rule::in(['restock', 'sale_offline', 'damaged', 'correction', 'returned', 'confirmed'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(['product' => $this->stock->set($product, $data['quantity'], $data['reason'], $data['note'] ?? null)]);
    }

    /** "Stock is still accurate" — refreshes the freshness timestamp without changing quantity. */
    public function confirmStock(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('manage', $product);

        return response()->json(['product' => $this->stock->set($product, (int) $product->stock_quantity, 'confirmed')]);
    }

    public function confirmAllStock(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $count = 0;
        Product::where('shop_id', $shop->id)->where('status', 'active')->each(function (Product $p) use (&$count) {
            $this->stock->set($p, (int) $p->stock_quantity, 'confirmed');
            $count++;
        });

        return response()->json(['message' => "Stock confirmed for {$count} products."]);
    }

    public function stockLogs(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('manage', $product);

        return response()->json($product->stockLogs()->with('user:id,name')->paginate(30));
    }

    public function deleteImage(Request $request, Product $product, int $image): JsonResponse
    {
        Gate::authorize('manage', $product);
        $product->images()->whereKey($image)->firstOrFail()->delete();

        return response()->json(['message' => 'Image removed.']);
    }

    private function storeImages(Product $product, Request $request): void
    {
        $sort = (int) $product->images()->max('sort_order');
        foreach ($request->file('images', []) as $img) {
            $product->images()->create(['path' => $this->files->storePublic($img, 'products/'.$product->id), 'sort_order' => ++$sort]);
        }
    }
}
