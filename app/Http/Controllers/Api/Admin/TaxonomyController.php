<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PhoneModel;
use App\Models\RepairCategory;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** CRUD for catalogue master data: categories, brands, phone models, areas and repair categories. */
class TaxonomyController extends Controller
{
    private const TYPES = [
        'categories' => Category::class,
        'brands' => Brand::class,
        'phone-models' => PhoneModel::class,
        'areas' => Area::class,
        'repair-categories' => RepairCategory::class,
    ];

    public function index(Request $request, string $type): JsonResponse
    {
        $class = $this->class($type);
        $q = $class::query();
        if ($type === 'phone-models') {
            $q->with('brand:id,name')->orderBy('brand_id')->orderByDesc('release_year');
            if ($request->filled('brand_id')) {
                $q->where('brand_id', $request->integer('brand_id'));
            }
        }
        if ($request->filled('q')) {
            $q->where('name', 'like', '%'.$request->input('q').'%');
        }
        match ($type) {
            'categories' => $q->withCount('products')->orderBy('sort_order'),
            'brands' => $q->withCount('phoneModels')->orderBy('name'),
            'repair-categories' => $q->orderBy('sort_order'),
            default => $q->orderBy('name'),
        };

        return response()->json($q->paginate(50)->withQueryString());
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $class = $this->class($type);
        $data = $request->validate($this->rules($type));
        $data['slug'] = $this->slug($class, $type === 'phone-models' ? (Brand::find($data['brand_id'])?->name.' '.$data['name']) : $data['name']);
        $item = $class::create($data);
        AuditLogger::log('taxonomy.created', $item, "Created {$type} entry: {$item->name}");

        return response()->json(['item' => $item], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $item = $this->class($type)::findOrFail($id);
        $data = $request->validate($this->rules($type, $item));
        $item->update($data);
        AuditLogger::log('taxonomy.updated', $item, "Updated {$type} entry: {$item->name}", ['changes' => $item->getChanges()]);

        return response()->json(['item' => $item]);
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        $item = $this->class($type)::findOrFail($id);
        try {
            $item->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'This entry is in use. Deactivate it instead of deleting.'], 422);
        }
        AuditLogger::log('taxonomy.deleted', null, "Deleted {$type} entry: {$item->name}", ['id' => $id]);

        return response()->json(['message' => 'Deleted.']);
    }

    /** @return class-string<Model> */
    private function class(string $type): string
    {
        return self::TYPES[$type] ?? abort(404);
    }

    private function rules(string $type, ?Model $item = null): array
    {
        $rules = ['name' => ['required', 'string', 'max:120'], 'is_active' => ['nullable', 'boolean']];

        return array_merge($rules, match ($type) {
            'categories' => ['icon' => ['nullable', 'string', 'max:40'], 'sort_order' => ['nullable', 'integer', 'min:0'], 'parent_id' => ['nullable', 'integer', 'exists:categories,id']],
            'repair-categories' => ['icon' => ['nullable', 'string', 'max:40'], 'sort_order' => ['nullable', 'integer', 'min:0']],
            'phone-models' => [
                'brand_id' => ['required', 'integer', 'exists:brands,id'],
                'release_year' => ['nullable', 'integer', 'between:2000,2100'],
                'name' => ['required', 'string', 'max:120', Rule::unique('phone_models')->where('brand_id', request('brand_id'))->ignore($item?->id)],
            ],
            'areas' => ['city' => ['required', 'string', 'max:80'], 'pincode' => ['nullable', 'digits:6']],
            default => [],
        });
    }

    private function slug(string $class, string $name): string
    {
        $base = Str::slug($name) ?: Str::random(6);
        $slug = $base;
        $i = 1;
        while ($class::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$i;
        }

        return $slug;
    }
}
