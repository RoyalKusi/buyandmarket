<?php

namespace Database\Factories;

use App\Models\Seller;
use App\Models\SellerOnboardingStep;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->withRole('seller'),
            'business_name' => fake()->company(),
            'status' => 'pending',
            'kyc_status' => 'pending',
        ];
    }

    /**
     * Every seller created through App\Services\SellerOnboardingService::
     * register() gets one onboarding_steps row per stepper step, with
     * business_info pre-completed. Factory-created sellers mirror that so
     * tests see the same shape a real registration produces.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Seller $seller) {
            foreach (SellerOnboardingStep::STEPS as $step) {
                $seller->onboardingSteps()->create([
                    'step' => $step,
                    'completed_at' => $step === 'business_info' ? now() : null,
                ]);
            }
        });
    }

    public function active(): static
    {
        return $this->state([
            'status' => 'active',
            'kyc_status' => 'approved',
        ])->withStore();
    }

    /**
     * TDD §3.1 module 3: one store per seller. Run 1.3 owns the actual
     * store-creation onboarding step; tests that need a store without
     * necessarily being fully active (e.g. mid-onboarding) use this
     * directly instead of active().
     */
    public function withStore(): static
    {
        return $this->afterCreating(function (Seller $seller) {
            Store::factory()->for($seller)->create();
        });
    }
}
