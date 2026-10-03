<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile Google/Facebook sign-in (App\Http\Controllers\Api\V1\
 * SocialAuthController): a social account is matched first by provider
 * ID, falling back to linking an existing email/password account by
 * email. `avatar_url` lets the mobile app show the buyer's real profile
 * photo instead of an initial-letter placeholder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('facebook_id')->nullable()->unique()->after('google_id');
            $table->string('avatar_url')->nullable()->after('facebook_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'facebook_id', 'avatar_url']);
        });
    }
};
