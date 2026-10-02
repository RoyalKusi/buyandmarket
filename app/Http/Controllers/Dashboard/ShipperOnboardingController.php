<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Shipper;
use App\Services\ShipperOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TDD §3.4 module 23 "become a shipper" web flow — deferred since
 * Run 1.7 (fully functional via the API, this adds the web presentation
 * only). Reuses ShipperOnboardingService, the same service the API
 * controller calls.
 */
class ShipperOnboardingController extends Controller
{
    public function show(Request $request): View
    {
        return view('dashboard.become-shipper', [
            'shipper' => $request->user()->shipper,
        ]);
    }

    public function register(Request $request, ShipperOnboardingService $onboardingService): RedirectResponse
    {
        $this->authorize('register', Shipper::class);

        $data = $request->validate([
            'business_name' => ['nullable', 'string', 'max:255'],
        ]);

        $onboardingService->register($request->user(), $data['business_name'] ?? null);

        return redirect()->route('dashboard')->with('status', 'You are now a shipper — see the Shipper section in the sidebar.');
    }
}
