<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\CurrencyConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends Controller
{
    /**
     * List active currencies.
     */
    public function index(): JsonResponse
    {
        $currencies = Currency::where('is_active', true)->get();
        return response()->json(['currencies' => $currencies]);
    }

    /**
     * Convert an amount between two currencies.
     */
    public function convert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'gt:0'],
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3'],
            'date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = CurrencyConversionService::convert(
            (float) $request->input('amount'),
            $request->input('from_currency'),
            $request->input('to_currency'),
            $request->input('date')
        );

        return response()->json($result);
    }
}
