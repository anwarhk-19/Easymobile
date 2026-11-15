<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Http\Requests\TechnicianProfileRequest;
use App\Models\TechnicianAgreement;
use App\Models\TechnicianProfile;
use App\Services\FileStore;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public const AGREEMENT_VERSION = '2026.1';

    public function __construct(private FileStore $files) {}

    public function show(Request $request): JsonResponse
    {
        $p = $request->user()->technicianProfile?->load(['area:id,name', 'serviceAreas:id,name', 'brands:id,name', 'phoneModels:id,name,brand_id', 'repairCategories:id,name', 'documents']);

        return response()->json(['profile' => $p]);
    }

    public function store(TechnicianProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($user->technicianProfile) {
            return response()->json(['message' => 'Profile already exists. Edit it instead.'], 422);
        }
        $data = $request->validated();
        $profile = DB::transaction(function () use ($user, $data, $request) {
            $profile = TechnicianProfile::create($this->attributes($data) + [
                'user_id' => $user->id,
                'slug' => $this->slug($data['name']),
                'status' => TechnicianProfile::STATUS_PENDING,
            ]);
            $this->syncRelations($profile, $data);
            $this->storeFiles($profile, $request);
            TechnicianAgreement::create(['user_id' => $user->id, 'technician_profile_id' => $profile->id, 'version' => self::AGREEMENT_VERSION, 'accepted_at' => now(), 'ip' => $request->ip()]);

            return $profile;
        });

        return response()->json(['profile' => $profile, 'message' => 'Profile submitted for review. You will be notified once approved.'], 201);
    }

    public function update(TechnicianProfileRequest $request): JsonResponse
    {
        $profile = $request->user()->technicianProfile ?? abort(404);
        $data = $request->validated();
        DB::transaction(function () use ($profile, $data, $request) {
            $attrs = $this->attributes($data);
            if ($profile->status === TechnicianProfile::STATUS_REJECTED) {
                $attrs += ['status' => TechnicianProfile::STATUS_PENDING, 'rejection_reason' => null];
            }
            $profile->update($attrs);
            $this->syncRelations($profile, $data);
            $this->storeFiles($profile, $request);
        });

        return response()->json(['profile' => $profile->fresh()]);
    }

    private function attributes(array $d): array
    {
        $a = Arr::only($d, ['name', 'shop_name', 'phone', 'whatsapp', 'address', 'area_id', 'experience_years', 'certifications', 'supported_models_note',
            'opens_at', 'closes_at', 'open_days', 'default_warranty_days', 'warranty_terms', 'bio']);
        $a['phone'] = Phone::normalize($a['phone']);
        $a['whatsapp'] = Phone::normalize($a['whatsapp'] ?? null) ?? $a['phone'];
        $a['pickup_available'] = (bool) ($d['pickup_available'] ?? false);
        $a['onsite_available'] = (bool) ($d['onsite_available'] ?? false);

        return $a;
    }

    private function syncRelations(TechnicianProfile $p, array $d): void
    {
        $p->serviceAreas()->sync($d['service_area_ids']);
        $p->brands()->sync($d['brand_ids']);
        $p->phoneModels()->sync($d['phone_model_ids'] ?? []);
        $p->repairCategories()->sync($d['repair_category_ids']);
    }

    private function storeFiles(TechnicianProfile $p, Request $request): void
    {
        if ($request->hasFile('photo')) {
            $p->update(['photo_path' => $this->files->storePublic($request->file('photo'), 'technicians/'.$p->id)]);
        }
        foreach ($request->input('documents', []) as $i => $doc) {
            if ($file = $request->file("documents.$i.file")) {
                $p->documents()->create([
                    'type' => $doc['type'],
                    'path' => $this->files->storePrivate($file, 'technician-documents/'.$p->id),
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'technician';
        $slug = $base;
        $i = 1;
        while (TechnicianProfile::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$i;
        }

        return $slug;
    }
}
