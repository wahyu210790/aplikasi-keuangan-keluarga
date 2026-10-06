<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Category;
use App\Models\Household;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    /**
     * List categories belonging to a household.
     *
     * @param  \App\Models\Household  $household
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Household $household): JsonResponse
    {
        $categories = Category::where('household_id', $household->id)
            ->orderBy('id', 'asc')
            ->get(['id', 'name', 'type', 'is_active']);

        return response()->json(['categories' => $categories]);
    }

    /**
     * Store a new category belonging to a household.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Household  $household
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Household $household): JsonResponse
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (is_string($value) && trim($value) === '') {
                        $fail('The name field must not be empty after trimming.');
                    }
                },
            ],
            'type' => ['required', Rule::in(['income', 'expense'])],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $name = trim($validated['name']);
        $type = $validated['type'];

        // Duplicate-active-category check for the same household
        $duplicateExists = Category::where('household_id', $household->id)
            ->where('is_active', true)
            ->where('name', $name)
            ->where('type', $type)
            ->exists();

        if ($duplicateExists) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['name' => ['The category already exists for this household.']],
            ], 422);
        }

        // Create new active category tied strictly to $household->id
        $category = Category::create([
            'household_id' => $household->id,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
        ]);

        // Log activity for category creation
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'category_created',
            'entity_type' => 'category',
            'entity_id' => $category->id,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Category created successfully.',
            'category' => $category->only(['id', 'name', 'type', 'is_active']),
        ], 201);
    }
    /**
     * Update a category belonging to a household.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Household  $household
     * @param  \App\Models\Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Household $household, Category $category): JsonResponse
    {
        // Ensure the category belongs to the given household.
        if ((int) $category->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Determine which updatable fields are present.
        $updatable = ['name', 'type'];
        $provided = array_intersect($updatable, array_keys($request->all()));
        if (empty($provided)) {
            return response()->json([
                'message' => 'At least one updatable field must be provided.',
            ], 422);
        }

        // Validation rules â€“ fields are optional but, if present, must be valid.
        $rules = [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (trim($value) === '') {
                        $fail('The name field must not be empty after trimming.');
                    }
                },
            ],
            'type' => ['sometimes', Rule::in(['income', 'expense'])],
        ];

        $validator = Validator::make($request->only($updatable), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        // Trim name if supplied.
        if (array_key_exists('name', $validated)) {
            $validated['name'] = trim($validated['name']);
        }

        // Duplicateâ€‘activeâ€‘category check (exclude the current category).
        $duplicateQuery = Category::where('household_id', $household->id)
            ->where('is_active', true)
            ->where('id', '!=', $category->id);
        $nameToCheck = $validated['name'] ?? $category->name;
        $typeToCheck = $validated['type'] ?? $category->type;
        $duplicateQuery->where('name', $nameToCheck)
            ->where('type', $typeToCheck);
        if ($duplicateQuery->exists()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['name' => ['The category already exists for this household.']],
            ], 422);
        }

        // Apply updates.
        $category->fill($validated);
        $category->save();

        // Log activity only if something actually changed.
        if ($category->wasChanged()) {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'household_id' => $household->id,
                'action' => 'category_updated',
                'entity_type' => 'category',
                'entity_id' => $category->id,
                'description' => null,
                'metadata' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);
        }

        return response()->json([
            'message' => 'Category updated successfully.',
            'category' => $category->only(['id', 'name', 'type', 'is_active']),
        ], 200);
    }
    /**
     * Archive a category belonging to a household.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Household  $household
     * @param  \App\Models\Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function archive(Request $request, Household $household, Category $category): JsonResponse
    {
        // Ensure the category belongs to the given household.
        if ((int) $category->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // If already archived, return success without creating a log.
        if ((bool) $category->is_active === false) {
            return response()->json([
                'message' => 'Category archived successfully.',
                'category' => $category->only(['id', 'name', 'type', 'is_active']),
            ], 200);
        }

        // Archive the category.
        $category->is_active = false;
        $category->save();

        // Log activity for the transition active -> archived.
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'category_archived',
            'entity_type' => 'category',
            'entity_id' => $category->id,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Category archived successfully.',
            'category' => $category->only(['id', 'name', 'type', 'is_active']),
        ], 200);
    }

}

