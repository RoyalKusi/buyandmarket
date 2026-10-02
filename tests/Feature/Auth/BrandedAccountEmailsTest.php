<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laravel's own VerifyEmail/ResetPassword notifications are rebuilt in
 * AppServiceProvider::boot() (toMailUsing) to render through this app's
 * branded email layout instead of the default Markdown notification
 * theme. Mail::fake() (used everywhere else notifications are tested)
 * intercepts dispatch before rendering, so it would never catch a typo
 * in these templates — this renders them for real, the same lesson
 * NotificationMailerTest's own render test already documents.
 */
class BrandedAccountEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_verify_email_notification_renders_the_branded_template(): void
    {
        $user = User::factory()->unverified()->create();

        $html = (new VerifyEmail)->toMail($user)->render();

        $this->assertStringContainsString('<!DOCTYPE html', $html);
        $this->assertStringNotContainsString('Facade root has not been set', $html);
        $this->assertStringContainsString('Confirm your email address', $html);
        $this->assertStringContainsString('BuyAndMarket', $html);
    }

    public function test_the_reset_password_notification_renders_the_branded_template(): void
    {
        $user = User::factory()->create();

        $html = (new ResetPassword('test-token'))->toMail($user)->render();

        $this->assertStringContainsString('<!DOCTYPE html', $html);
        $this->assertStringNotContainsString('Facade root has not been set', $html);
        $this->assertStringContainsString('Reset your password', $html);
        $this->assertStringContainsString('BuyAndMarket', $html);
    }
}
