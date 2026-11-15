<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\PhoneModel;
use App\Models\Product;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\TechnicianProfile;
use App\Services\SearchService;
use App\Services\WhatsAppLinkBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogueController extends Controller
{
    public function __construct(private SearchService $search, private WhatsAppLinkBuilder $wa) {}

    public function meta(): JsonResponse
    {
        $areas = Area::active()->orderBy('name')->get(['id', 'name', 'slug', 'city']);

        return response()->json([
            'cities' => $areas->pluck('city')->unique()->values(),
            'areas' => $areas,
            'categories' => Category::active()->orderBy('sort_order')->get(['id', 'name', 'slug', 'icon', 'parent_id']),
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name', 'slug', 'logo_path']),
            'repair_categories' => RepairCategory::active()->orderBy('sort_order')->get(['id', 'name', 'slug', 'icon']),
            'settings' => [
                'platform_name' => Setting::get('platform_name'),
                'support_phone' => Setting::get('support_phone'),
                'support_email' => Setting::get('support_email'),
                'default_city' => Setting::get('default_city'),
            ],
            'enums' => [
                'availability' => Product::AVAILABILITY,
                'parts_types' => RepairRequest::PARTS_TYPES,
                'service_modes' => RepairRequest::SERVICE_MODES,
                'ticket_categories' => SupportTicket::CATEGORIES,
                'payment_methods' => Order::PAYMENT_METHODS,
            ],
        ]);
    }

    public function phoneModels(Request $request): JsonResponse
    {
        $request->validate(['brand_id' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:60']]);
        $q = PhoneModel::active()->with('brand:id,name')->orderByDesc('release_year')->orderBy('name');
        if ($request->filled('brand_id')) {
            $q->where('brand_id', $request->integer('brand_id'));
        }
        if ($request->filled('q')) {
            $q->where('name', 'like', '%'.$request->input('q').'%');
        }

        return response()->json($q->limit(200)->get(['id', 'brand_id', 'name', 'slug', 'release_year']));
    }

    public function home(Request $request): JsonResponse
    {
        $areaId = $request->integer('area_id') ?: null;
        $accessoryCats = Category::whereIn('slug', ['chargers', 'earphones', 'mobile-covers', 'screen-protectors', 'power-banks', 'cables'])->pluck('id');
        $visible = fn () => $this->search->products(['area_id' => $areaId]);

        return response()->json([
            'featured_shops' => $this->search->shops(['area_id' => $areaId])->where('is_featured', true)->limit(8)->get(),
            'recent_products' => $visible()->reorder()->latest('products.created_at')->limit(8)->get(),
            'popular_accessories' => $visible()->reorder()->whereIn('products.category_id', $accessoryCats)
                ->whereIn('products.availability', ['in_stock', 'limited'])->orderByDesc('products.views_count')->limit(8)->get(),
            'top_technicians' => $this->search->technicians(['area_id' => $areaId])->limit(6)->get(),
            'repair_categories' => RepairCategory::active()->orderBy('sort_order')->withCount(['technicians' => fn ($t) => $t->where('status', 'approved')])->get(),
            'banners' => Setting::get('home_banners', [
                ['title' => 'Every mobile shop in Bhatkal, one search away', 'subtitle' => 'Compare prices and check live stock before you visit.', 'cta' => 'Browse products', 'link' => '/search', 'tone' => 'brand'],
                ['title' => 'Cracked screen? Battery draining?', 'subtitle' => 'Get quotes from verified local technicians. Pay only after you approve.', 'cta' => 'Book a repair', 'link' => '/repairs/new', 'tone' => 'accent'],
            ]),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $f = $request->validate(['q' => ['required', 'string', 'max:100'], 'area_id' => ['nullable', 'integer']]);
        $products = $this->search->products($f)->limit(12)->get();
        $shops = $this->search->shops($f)->limit(6)->get();
        $techs = $this->search->technicians($f)->limit(6)->get();
        $repairCats = RepairCategory::active()->where('name', 'like', '%'.$f['q'].'%')->get(['id', 'name', 'slug']);
        $total = $products->count() + $shops->count() + $techs->count() + $repairCats->count();
        $this->search->log($request->user('sanctum')?->id, $f['q'], 'all', $f, $total);

        return response()->json([
            'query' => $f['q'],
            'total' => $total,
            'products' => $products,
            'shops' => $shops,
            'technicians' => $techs,
            'repair_categories' => $repairCats,
            'suggestions' => $total === 0 ? $this->suggestions() : [],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:60'],
            'brand_id' => ['nullable', 'integer'],
            'phone_model_id' => ['nullable', 'integer'],
            'shop_id' => ['nullable', 'integer'],
            'area_id' => ['nullable', 'integer'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'availability' => ['nullable', Rule::in([...Product::AVAILABILITY, 'available'])],
            'delivery' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['relevance', 'price_asc', 'price_desc', 'rating', 'newest'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);
        $page = $this->search->products($f)->paginate($f['per_page'] ?? 24)->withQueryString();
        if (! empty($f['q']) && $page->currentPage() === 1) {
            $this->search->log($request->user('sanctum')?->id, $f['q'], 'products', $f, $page->total());
        }
        $payload = $page->toArray();
        if ($page->total() === 0) {
            $payload['suggestions'] = $this->suggestions();
        }

        return response()->json($payload);
    }

    public function product(Request $request, string $slug): JsonResponse
    {
        $product = Product::visible()->where('slug', $slug)
            ->with(['images', 'category:id,name,slug', 'brand:id,name', 'compatibleModels.brand:id,name', 'shop.area:id,name'])->firstOrFail();
        $product->increment('views_count');

        $related = $this->search->products(['category' => $product->category_id])->where('products.id', '!=', $product->id)->limit(6)->get();
        $otherShops = Product::visible()->where('products.id', '!=', $product->id)->where('products.shop_id', '!=', $product->shop_id)
            ->where('products.name', $product->name)->with('shop:id,name,slug')->limit(5)->get(['id', 'slug', 'shop_id', 'price', 'discount_price', 'availability', 'stock_confirmed_at']);

        return response()->json([
            'product' => $product,
            'shop' => $product->shop->append('maps_link'),
            'whatsapp_link' => $this->wa->forProduct($product),
            'reviews' => $this->reviewsFor($product),
            'related' => $related,
            'same_product_other_shops' => $otherShops,
        ]);
    }

    public function shops(Request $request): JsonResponse
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'area_id' => ['nullable', 'integer'], 'delivery' => ['nullable', 'boolean'], 'sort' => ['nullable', 'string']]);
        $page = $this->search->shops($f)->paginate(20)->withQueryString();
        if (! empty($f['q']) && $page->currentPage() === 1) {
            $this->search->log($request->user('sanctum')?->id, $f['q'], 'shops', $f, $page->total());
        }

        return response()->json($page);
    }

    public function shop(string $slug): JsonResponse
    {
        $shop = Shop::published()->where('slug', $slug)->with(['area:id,name', 'photos'])->firstOrFail();

        return response()->json([
            'shop' => $shop->append('maps_link'),
            'whatsapp_link' => $this->wa->forShop($shop),
            'categories' => Category::whereHas('products', fn ($p) => $p->where('shop_id', $shop->id)->where('status', 'active')->where('is_suspended', false))->get(['id', 'name', 'slug']),
            'reviews' => $this->reviewsFor($shop),
        ]);
    }

    public function technicians(Request $request): JsonResponse
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'repair_category' => ['nullable', 'string', 'max:60'], 'brand_id' => ['nullable', 'integer'],
            'area_id' => ['nullable', 'integer'], 'pickup' => ['nullable', 'boolean']]);
        $page = $this->search->technicians($f)->paginate(20)->withQueryString();
        if (! empty($f['q']) && $page->currentPage() === 1) {
            $this->search->log($request->user('sanctum')?->id, $f['q'], 'technicians', $f, $page->total());
        }

        return response()->json($page);
    }

    public function technician(string $slug): JsonResponse
    {
        $tech = TechnicianProfile::published()->where('slug', $slug)
            ->with(['area:id,name', 'serviceAreas:id,name', 'brands:id,name', 'phoneModels:id,name,brand_id', 'repairCategories:id,name,slug'])
            ->withCount(['repairRequests as completed_repairs' => fn ($q) => $q->where('status', 'completed')])->firstOrFail();

        return response()->json([
            'technician' => $tech,
            'whatsapp_link' => $this->wa->forTechnician($tech),
            'reviews' => $this->reviewsFor($tech),
        ]);
    }

    private function reviewsFor($model)
    {
        return Review::where('reviewable_type', $model->getMorphClass())->where('reviewable_id', $model->getKey())
            ->where('status', 'approved')->with('customer')->latest()->limit(20)->get(['id', 'customer_id', 'rating', 'comment', 'created_at']);
    }

    private function suggestions(): array
    {
        return [
            'tips' => ['Check the spelling or try a shorter term, e.g. "type c" instead of "type-c fast charging cable".',
                'Search by brand or model, e.g. "Redmi Note 13" or "iPhone 15".',
                'Browse by category, or send an enquiry to a shop — they may have it in stock.'],
            'popular_categories' => Category::active()->withCount(['products' => fn ($p) => $p->where('status', 'active')])->orderByDesc('products_count')->limit(6)->get(['id', 'name', 'slug']),
        ];
    }
}
