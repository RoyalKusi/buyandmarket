<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_can_register_and_is_assigned_the_buyer_role(): void
    {
        $response = $this->post('/register', [
            'name' => 'Tinashe Moyo',
            'email' => 'tinashe@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $this->assertAuthenticated();

        $user = User::where('email', 'tinashe@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('buyer'));
        $this->assertFalse($user->hasRole('admin'));
    }
}
