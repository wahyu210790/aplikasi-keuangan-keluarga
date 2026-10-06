<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    /**
     * Display a listing of budgets for the given household.
     */
    public function index(Request $request, Household $household): JsonResponse
    {
        $query = Budget::with('category:id,name,type')
            ->where('household_id', $household->id);

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $budgets = $query->orderBy('start_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['budgets' => $budgets], 200);
    }

    /**
     * Store a new budget for the given household.
     */
    public function store(Request $request, Household $household): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'period_type' => ['required', Rule::in(['monthly', 'custom'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request, $household) {
            $categoryId = $request->input('category_id');
            if (! is_null($categoryId)) {
                $category = Category::where('household_id', $household->id)
                    ->where('id', $categoryId)
                    ->first();
                if (! $category) {
                    $validator->errors()->add('category_id', 'The selected category is invalid.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $budget = Budget::create([
            'household_id' => $household->id,
            'category_id' => $request->input('category_id'),
            'name' => trim($request->input('name')),
            'amount' => number_format($request->input('amount'), 2, '.', ''),
            'period_type' => $request->input('period_type'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $budget->load('category:id,name,type');

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'budget_created',
            'entity_type' => 'budget',
            'entity_id' => $budget->id,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Budget created successfully.',
            'budget' => $budget,
        ], 201);
    }

    /**
     * Display the specified budget for the given household.
     */
    public function show(Household $household, Budget $budget): JsonResponse
    {
        if ((int) $budget->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $budget->load('category:id,name,type');

        return response()->json(['budget' => $budget], 200);
    }

    /**
     * Update an existing budget for the given household.
     */
    public function update(Request $request, Household $household, Budget $budget): JsonResponse
    {
        if ((int) $budget->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $updatable = ['name', 'category_id', 'amount', 'period_type', 'start_date', 'end_date', 'is_active'];
        $provided = array_intersect($updatable, array_keys($request->all()));
        if (empty($provided)) {
            return response()->json([
                'message' => 'At least one updatable field must be provided.',
            ], 422);
        }

        $validator = Validator::make($request->only($updatable), [
            'name' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'period_type' => ['sometimes', Rule::in(['monthly', 'custom'])],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request, $household, $budget) {
            // Check category if provided
            if ($request->has('category_id') && ! is_null($request->input('category_id'))) {
                $categoryId = $request->input('category_id');
                $category = Category::where('household_id', $household->id)
                    ->where('id', $categoryId)
                    ->first();
                if (! $category) {
                    $validator->errors()->add('category_id', 'The selected category is invalid.');
                }
            }

            // Check date range consistency
            $startDate = $request->input('start_date', $budget->start_date);
            $endDate = $request->input('end_date', $budget->end_date);
            if ($startDate && $endDate && strtotime($endDate) < strtotime($startDate)) {
                $validator->errors()->add('end_date', 'The end_date must be a date after or equal to start_date.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [];
        if ($request->filled('name')) $updateData['name'] = trim($request->input('name'));
        if ($request->has('category_id')) $updateData['category_id'] = $request->input('category_id');
        if ($request->filled('amount')) $updateData['amount'] = number_format($request->input('amount'), 2, '.', '');
        if ($request->filled('period_type')) $updateData['period_type'] = $request->input('period_type');
        if ($request->filled('start_date')) $updateData['start_date'] = $request->input('start_date');
        if ($request->filled('end_date')) $updateData['end_date'] = $request->input('end_date');
        if ($request->has('is_active')) $updateData['is_active'] = $request->boolean('is_active');

        $budget->update($updateData);
        $budget->load('category:id,name,type');

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'budget_updated',
            'entity_type' => 'budget',
            'entity_id' => $budget->id,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Budget updated successfully.',
            'budget' => $budget,
        ], 200);
    }

    /**
     * Hard delete a budget for the given household.
     */
    public function destroy(Request $request, Household $household, Budget $budget): JsonResponse
    {
        if ((int) $budget->household_id !== (int) $household->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $budgetId = $budget->id;
        $budget->delete();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'budget_deleted',
            'entity_type' => 'budget',
            'entity_id' => $budgetId,
            'description' => null,
            'metadata' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Budget deleted successfully.',
        ], 200);
    }
}
