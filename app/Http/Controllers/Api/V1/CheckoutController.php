<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CheckoutController extends Controller
{
    public function store(Request $request, CartService $cartService, CheckoutService $checkoutService): JsonResponse
    {
        $data = $request->validate([
            // 'required_without:user' would check for a literal 'user'
            // *input field*, which never exists — the actual condition is
            // "no authenticated user", which isn't expressible as a
            // static rule string, hence the conditional array below.
            'guest_email' => [$request->user() ? 'nullable' : 'required', 'email'],
            'guest_phone' => ['nullable', 'string'],
        ]);

        $cart = $cartService->getOrCreateCart($request->user(), $request->session()->getId());

        $session = $checkoutService->start(
            $cart,
            $request->user(),
            $data['guest_email'] ?? null,
            $data['guest_phone'] ?? null,
        );

        return response()->json(['data' => $session], 201);
    }

    public function show(CheckoutSession $checkoutSession): JsonResponse
    {
        $this->authorizeSession($checkoutSession);

        return response()->json(['data' => $checkoutSession]);
    }

    public function setAddress(Request $request, CheckoutSession $checkoutSession, CheckoutService $checkoutService): JsonResponse
    {
        $this->authorizeSession($checkoutSession);

        $data = $request->validate(['address_id' => ['required', 'exists:addresses,id']]);
        $address = Address::findOrFail($data['address_id']);

        abort_unless($request->user() && $address->user_id === $request->user()->id, 403);

        return response()->json(['data' => $checkoutService->setAddress($checkoutSession, $address)]);
    }

    public function setDelivery(Request $request, CheckoutSession $checkoutSession, CheckoutService $checkoutService): JsonResponse
    {
        $this->authorizeSession($checkoutSession);

        $data = $request->validate([
            'selection' => ['required', 'array'],
            'selection.*.fee' => ['required', 'decimal:0,2'],
        ]);

        return response()->json([
            'data' => $checkoutService->setDeliveryMethod($checkoutSession, $data['selection']),
        ]);
    }

    /**
     * TDD §7.1: "any state-changing endpoint that can be safely retried
     * accepts an Idempotency-Key header, checked against a short-TTL
     * cache before processing."
     */
    public function initiatePayment(Request $request, CheckoutSession $checkoutSession, CheckoutService $checkoutService): JsonResponse
    {
        $this->authorizeSession($checkoutSession);

        $data = $request->validate(['provider' => ['required', 'in:pesepay,paynow']]);

        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey !== null) {
            $cacheKey = "checkout:payment:{$checkoutSession->id}:{$idempotencyKey}";

            $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($checkoutSession, $data, $checkoutService) {
                return $this->initiate($checkoutSession, $data['provider'], $checkoutService);
            });

            return response()->json(['data' => $payload]);
        }

        return response()->json(['data' => $this->initiate($checkoutSession, $data['provider'], $checkoutService)]);
    }

    /**
     * @return array{payment: Payment, redirect_url: ?string, instructions: ?string}
     */
    private function initiate(CheckoutSession $checkoutSession, string $provider, CheckoutService $checkoutService): array
    {
        $result = $checkoutService->initiatePayment($checkoutSession, $provider);

        return [
            'payment' => $result->payment,
            'redirect_url' => $result->redirectUrl,
            'instructions' => $result->instructions,
        ];
    }

    private function authorizeSession(CheckoutSession $checkoutSession): void
    {
        $request = request();
        $owns = $request->user() !== null
            ? $checkoutSession->user_id === $request->user()->id
            : $checkoutSession->cart->session_id === $request->session()->getId();

        abort_unless($owns, 404);
    }
}
