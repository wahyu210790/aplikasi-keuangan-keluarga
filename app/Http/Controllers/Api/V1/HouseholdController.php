<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HouseholdController extends Controller
{
    /**
     * Store a newly created household.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate incoming data
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'filled'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $user = $request->user();

        return DB::transaction(function () use ($validated, $user) {
            // Create household
            $household = Household::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            // Attach owner as household member
            HouseholdMember::create([
                'user_id' => $user->id,
                'household_id' => $household->id,
                'role' => 'household_owner',
            ]);

            // Create activity log
            ActivityLog::create([
                'user_id' => $user->id,
                'household_id' => $household->id,
                'action' => 'household_created',
                'entity_type' => 'household',
                'entity_id' => $household->id,
                'description' => "Household \"{$household->name}\" created",
                'created_at' => now(),
            ]);

            return response()->json([
                'message' => 'Household berhasil dibuat.',
                'household' => [
                    'id' => $household->id,
                    'name' => $household->name,
                    'description' => $household->description,
                ],
                'membership' => [
                    'role' => 'household_owner',
                ],
            ], 201);
        });
    }
    

    /**
     * Display the specified household profile.
     *
     * @param  \App\Models\Household  $household
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Household $household)
    {
        $this->authorize('view', $household);

        return response()->json([
            'household' => [
                'id' => $household->id,
                'name' => $household->name,
                'description' => $household->description,
                'created_at' => $household->created_at,
                'updated_at' => $household->updated_at,
            ],
        ]);
    }

    /**
     * Update the specified household profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Household  $household
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Household $household)
    {
        $this->authorize('update', $household);

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'filled'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $validator->after(function ($v) use ($request) {
            if (empty($request->only(['name', 'description']))) {
                $v->errors()->add('field', 'At least one field must be provided.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $household->update($validated);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'household_updated',
            'entity_type' => 'household',
            'entity_id' => $household->id,
            'description' => "Household \"{$household->name}\" updated",
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Household updated successfully.',
            'household' => [
                'id' => $household->id,
                'name' => $household->name,
                'description' => $household->description,
                'created_at' => $household->created_at,
                'updated_at' => $household->updated_at,
            ],
        ]);
    }
    /**
     * Get household settings.
     *
     * @param  \App\Models\Household  $household
     * @return \Illuminate\Http\JsonResponse
     */
    public function settings(Household $household)
    {
        $this->authorize('view', $household);

        return response()->json([
            'settings' => $household->settings,
        ]);
}
/**
     * Update household settings (owner only).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Household  $household
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSettings(Request $request, Household $household)
    {
        $this->authorize('update', $household);

        // Validate payload contains a settings array/object.
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $household->settings = $validated['settings'];
                $household->save();

        // Record activity log for successful settings update
        \App\Models\ActivityLog::create([
            'user_id' => $request->user()->id,
            'household_id' => $household->id,
            'action' => 'household_settings_updated',
            'entity_type' => 'household',
            'entity_id' => $household->id,
        ]);


        return response()->json([
            'settings' => $household->settings,
        ]);
    }

}


