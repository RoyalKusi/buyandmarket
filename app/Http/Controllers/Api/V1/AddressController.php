<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile app groundwork: the web dashboard's address book
 * (BuyerController::addresses()/storeAddress()/destroyAddress()) had
 * no API equivalent — a mobile buyer had no way to add a delivery
 * address at all, only to pick an existing one by ID during checkout
 * (CheckoutController::setAddress). Same validation and "at most one
 * default" invariant as the web form.
 */
class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user('sanctum')->addresses()->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'street_address' => ['required', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $isDefault = $request->boolean('is_default');

        if ($isDefault) {
            $request->user('sanctum')->addresses()->update(['is_default' => false]);
        }

        $address = $request->user('sanctum')->addresses()->create([...$data, 'is_default' => $isDefault]);

        return response()->json(['data' => $address], 201);
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user('sanctum')->id, 404);

        $address->delete();

        return response()->json(status: 204);
    }
}
