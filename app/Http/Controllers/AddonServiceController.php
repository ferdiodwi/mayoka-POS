<?php

namespace App\Http\Controllers;

use App\Models\AddonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddonServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $addons = AddonService::orderBy('name')->get();
        return response()->json(['addon_services' => $addons]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
        ]);

        $addon = AddonService::create($validated);

        return response()->json([
            'message' => 'Jasa tambahan berhasil dibuat.',
            'addon_service' => $addon,
        ], 201);
    }

    public function update(Request $request, AddonService $addonService): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $addonService->update($validated);

        return response()->json([
            'message' => 'Jasa tambahan berhasil diperbarui.',
            'addon_service' => $addonService,
        ]);
    }

    public function destroy(AddonService $addonService): JsonResponse
    {
        $addonService->update(['is_active' => false]);
        return response()->json(['message' => 'Jasa tambahan berhasil dinonaktifkan.']);
    }
}
