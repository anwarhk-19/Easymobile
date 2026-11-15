<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SearchLog;
use App\Models\Shop;
use App\Models\TechnicianProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SearchService
{
    public function products(array $f): Builder
    {
        $q = Product::query()->visible()
            ->with(['primaryImage', 'shop:id,name,slug,area_id,whatsapp,phone,delivery_available,pickup_available,rating_avg', 'shop.area:id,name', 'brand:id,name', 'category:id,name,slug']);

        if ($term = trim((string) ($f['q'] ?? ''))) {
            $words = array_filter(preg_split('/\s+/', Str::limit($term, 100, '')));
            foreach ($words as $w) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $w).'%';
                $q->where(function (Builder $s) use ($like) {
                    $s->where('products.name', 'like', $like)
                        ->orWhere('products.description', 'like', $like)
                        ->orWhere('products.sku', 'like', $like)
                        ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like))
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like))
                        ->orWhereHas('compatibleModels', fn ($m) => $m->where('phone_models.name', 'like', $like))
                        ->orWhereHas('shop', fn ($sh) => $sh->where('name', 'like', $like));
                });
            }
        }
        if (! empty($f['category'])) {
            $q->whereHas('category', fn ($c) => is_numeric($f['category']) ? $c->whereKey($f['category']) : $c->where('slug', $f['category']));
        }
        if (! empty($f['brand_id'])) {
            $q->where(fn ($s) => $s->where('products.brand_id', $f['brand_id'])
                ->orWhereHas('compatibleModels', fn ($m) => $m->where('phone_models.brand_id', $f['brand_id'])));
        }
        if (! empty($f['phone_model_id'])) {
            $q->where(fn ($s) => $s->whereHas('compatibleModels', fn ($m) => $m->where('phone_models.id', $f['phone_model_id'])));
        }
        if (! empty($f['shop_id'])) {
            $q->where('products.shop_id', $f['shop_id']);
        }
        if (! empty($f['area_id'])) {
            $q->whereHas('shop', fn ($s) => $s->where('area_id', $f['area_id']));
        }
        $price = 'COALESCE(products.discount_price, products.price)';
        if (isset($f['min_price']) && $f['min_price'] !== '') {
            $q->whereRaw("{$price} >= CAST(? AS DECIMAL(12,2))", [(float) $f['min_price']]);
        }
        if (isset($f['max_price']) && $f['max_price'] !== '') {
            $q->whereRaw("{$price} <= CAST(? AS DECIMAL(12,2))", [(float) $f['max_price']]);
        }
        if (! empty($f['availability'])) {
            $f['availability'] === 'available'
                ? $q->whereIn('products.availability', ['in_stock', 'limited'])
                : $q->where('products.availability', $f['availability']);
        }
        if (! empty($f['delivery'])) {
            $q->whereHas('shop', fn ($s) => $s->where('delivery_available', true));
        }

        match ($f['sort'] ?? 'relevance') {
            'price_asc' => $q->orderByRaw("{$price} asc"),
            'price_desc' => $q->orderByRaw("{$price} desc"),
            'rating' => $q->orderByDesc('products.rating_avg'),
            'newest' => $q->latest('products.created_at'),
            default => $q->orderByRaw("CASE WHEN products.availability IN ('in_stock','limited') THEN 0 ELSE 1 END")
                ->orderByDesc('products.stock_confirmed_at'),
        };

        return $q;
    }

    public function shops(array $f): Builder
    {
        $q = Shop::published()->with('area:id,name')->withCount(['products' => fn ($p) => $p->where('status', 'active')->where('is_suspended', false)]);
        if ($term = trim((string) ($f['q'] ?? ''))) {
            $like = '%'.$term.'%';
            $q->where(fn ($s) => $s->where('name', 'like', $like)->orWhere('address', 'like', $like)->orWhere('description', 'like', $like)
                ->orWhereHas('area', fn ($a) => $a->where('name', 'like', $like)));
        }
        if (! empty($f['area_id'])) {
            $q->where('area_id', $f['area_id']);
        }
        if (! empty($f['delivery'])) {
            $q->where('delivery_available', true);
        }
        if (! empty($f['featured'])) {
            $q->where('is_featured', true);
        }

        return ($f['sort'] ?? '') === 'name' ? $q->orderBy('name') : $q->orderByDesc('is_featured')->orderByDesc('rating_avg')->orderBy('name');
    }

    public function technicians(array $f): Builder
    {
        $q = TechnicianProfile::published()->with(['area:id,name', 'repairCategories:id,name,slug', 'brands:id,name']);
        if ($term = trim((string) ($f['q'] ?? ''))) {
            $like = '%'.$term.'%';
            $q->where(fn ($s) => $s->where('name', 'like', $like)->orWhere('shop_name', 'like', $like)->orWhere('supported_models_note', 'like', $like)
                ->orWhereHas('repairCategories', fn ($c) => $c->where('name', 'like', $like))
                ->orWhereHas('brands', fn ($b) => $b->where('name', 'like', $like))
                ->orWhereHas('area', fn ($a) => $a->where('name', 'like', $like)));
        }
        if (! empty($f['repair_category'])) {
            $q->whereHas('repairCategories', fn ($c) => is_numeric($f['repair_category']) ? $c->whereKey($f['repair_category']) : $c->where('slug', $f['repair_category']));
        }
        if (! empty($f['brand_id'])) {
            $q->whereHas('brands', fn ($b) => $b->whereKey($f['brand_id']));
        }
        if (! empty($f['area_id'])) {
            $q->where(fn ($s) => $s->where('area_id', $f['area_id'])->orWhereHas('serviceAreas', fn ($a) => $a->whereKey($f['area_id'])));
        }
        if (! empty($f['pickup'])) {
            $q->where('pickup_available', true);
        }

        return $q->orderByDesc('rating_avg')->orderByDesc('rating_count')->orderBy('name');
    }

    public function log(?int $userId, string $query, string $type, array $filters, int $count): void
    {
        $query = mb_strtolower(trim($query));
        if ($query === '') {
            return;
        }
        SearchLog::create([
            'user_id' => $userId,
            'query' => mb_substr($query, 0, 190),
            'type' => $type,
            'filters' => array_filter($filters, fn ($v) => $v !== null && $v !== '') ?: null,
            'results_count' => $count,
            'created_at' => now(),
        ]);
    }
}
