<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * List all customers (owner, paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($request->has('search')) {
            $s = $request->get('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        if ($request->get('type')) {
            $query->where('type', $request->get('type'));
        }

        $customers = $query->orderBy('name')->paginate(20);
        return response()->json($customers);
    }

    /**
     * Search customers for POS autocomplete (kasir + owner).
     */
    public function search(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        $customers = Customer::active()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('code', 'like', "%{$q}%");
            })
            ->orderByRaw("CASE WHEN type = 'umum' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(['customers' => $customers]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:20|unique:customers,code',
            'name' => 'required|string|max:100',
            'type' => 'required|in:umum,member',
            'price_level' => 'required|in:h1,h2,h3',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        if (empty($validated['code'])) {
            $prefix = $validated['type'] === 'member' ? 'MBR-' : 'CUS-';
            $nextId = (Customer::max('id') ?? 0) + 1;
            $validated['code'] = $prefix . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            
            // Ensure unique
            while (Customer::where('code', $validated['code'])->exists()) {
                $nextId++;
                $validated['code'] = $prefix . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }
        }

        $customer = Customer::create($validated);
        return response()->json(['customer' => $customer, 'message' => 'Customer berhasil ditambahkan.'], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:customers,code,' . $customer->id,
            'name' => 'required|string|max:100',
            'type' => 'required|in:umum,member',
            'price_level' => 'required|in:h1,h2,h3',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $customer->update($validated);
        return response()->json(['customer' => $customer, 'message' => 'Customer berhasil diupdate.']);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        if ($customer->code === '10001') {
            return response()->json(['message' => 'Customer UMUM tidak bisa dihapus.'], 422);
        }
        $customer->delete();
        return response()->json(['message' => 'Customer berhasil dihapus.']);
    }
}
