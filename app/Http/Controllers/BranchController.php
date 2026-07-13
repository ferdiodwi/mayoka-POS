<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Branch;

class BranchController extends Controller
{
    public function index()
    {
        // Admin/Owner sees all branches, others only see active or their own. 
        // For simplicity and since only owner manages branches, we return all branches.
        return response()->json([
            'branches' => Branch::all()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'receipt_footer' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $branch = Branch::create($validated);

        return response()->json([
            'message' => 'Cabang berhasil ditambahkan.',
            'branch' => $branch
        ], 201);
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'receipt_footer' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $branch->update($validated);

        return response()->json([
            'message' => 'Cabang berhasil diperbarui.',
            'branch' => $branch
        ]);
    }

    public function destroy(Branch $branch)
    {
        if ($branch->id === 1) {
            return response()->json([
                'message' => 'Cabang Pusat tidak dapat dihapus.'
            ], 403);
        }

        $branch->delete();

        return response()->json([
            'message' => 'Cabang berhasil dihapus.'
        ]);
    }
}
