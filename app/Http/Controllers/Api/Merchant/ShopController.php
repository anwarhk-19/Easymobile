<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShopRequest;
use App\Models\MerchantAgreement;
use App\Models\Shop;
use App\Services\FileStore;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    public const AGREEMENT_VERSION = '2026.1';

    public function __construct(private FileStore $files) {}

    public function show(Request $request): JsonResponse
    {
        $shop = $request->user()->shop?->load(['area:id,name', 'photos', 'documents']);

        return response()->json(['shop' => $shop?->append('maps_link'), 'profile' => $request->user()->merchantProfile]);
    }

    /** Registers the merchant's shop. It stays hidden from customers until an admin approves it. */
    public function store(ShopRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($user->shop) {
            return response()->json(['message' => 'You have already registered a shop. Edit it instead.'], 422);
        }
        $data = $request->validated();

        $shop = DB::transaction(function () use ($user, $data, $request) {
            $user->merchantProfile()->updateOrCreate(['user_id' => $user->id], ['owner_name' => $data['owner_name'], 'gstin' => $data['gstin'] ?? null]);
            $shop = Shop::create($this->attributes($data) + [
                'merchant_id' => $user->id,
                'slug' => $this->uniqueSlug($data['name']),
                'status' => Shop::STATUS_PENDING,
            ]);
            $this->storeFiles($shop, $request);
            MerchantAgreement::create(['user_id' => $user->id, 'shop_id' => $shop->id, 'version' => self::AGREEMENT_VERSION, 'accepted_at' => now(), 'ip' => $request->ip()]);

            return $shop;
        });

        return response()->json(['shop' => $shop->load('photos', 'documents'), 'message' => 'Shop submitted for verification. We will notify you once it is approved.'], 201);
    }

    public function update(ShopRequest $request): JsonResponse
    {
        $shop = $request->user()->shop ?? abort(404);
        $data = $request->validated();
        DB::transaction(function () use ($shop, $data, $request) {
            $request->user()->merchantProfile()->updateOrCreate(['user_id' => $request->user()->id], ['owner_name' => $data['owner_name'], 'gstin' => $data['gstin'] ?? null]);
            $attrs = $this->attributes($data);
            // Changing core identity details of a rejected shop re-submits it for review.
            if ($shop->status === Shop::STATUS_REJECTED) {
                $attrs['status'] = Shop::STATUS_PENDING;
                $attrs['rejection_reason'] = null;
            }
            $shop->update($attrs);
            $this->storeFiles($shop, $request);
        });

        return response()->json(['shop' => $shop->fresh(['photos', 'documents', 'area'])]);
    }

    public function deletePhoto(Request $request, int $photo): JsonResponse
    {
        $shop = $request->user()->shop ?? abort(404);
        $shop->photos()->whereKey($photo)->firstOrFail()->delete();

        return response()->json(['message' => 'Photo removed.']);
    }

    private function attributes(array $data): array
    {
        $a = Arr::only($data, ['name', 'owner_name', 'phone', 'whatsapp', 'address', 'area_id', 'maps_url', 'latitude', 'longitude',
            'opens_at', 'closes_at', 'open_days', 'description', 'delivery_fee', 'delivery_notes']);
        $a['phone'] = Phone::normalize($a['phone']);
        $a['whatsapp'] = Phone::normalize($a['whatsapp'] ?? null) ?? $a['phone'];
        $a['pickup_available'] = (bool) ($data['pickup_available'] ?? true);
        $a['delivery_available'] = (bool) ($data['delivery_available'] ?? false);
        $a['delivery_fee'] = $a['delivery_fee'] ?? 0;

        return $a;
    }

    private function storeFiles(Shop $shop, Request $request): void
    {
        if ($request->hasFile('logo')) {
            $shop->update(['logo_path' => $this->files->storePublic($request->file('logo'), 'shops/'.$shop->id)]);
        }
        $sort = (int) $shop->photos()->max('sort_order');
        foreach ($request->file('photos', []) as $photo) {
            $shop->photos()->create(['path' => $this->files->storePublic($photo, 'shops/'.$shop->id.'/photos'), 'sort_order' => ++$sort]);
        }
        foreach ($request->input('documents', []) as $i => $doc) {
            $file = $request->file("documents.$i.file");
            if (! $file) {
                continue;
            }
            $shop->documents()->create([
                'type' => $doc['type'],
                'path' => $this->files->storePrivate($file, 'shop-documents/'.$shop->id),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        $i = 1;
        while (Shop::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$i;
        }

        return $slug;
    }
}
