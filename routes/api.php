<?php

use App\Http\Controllers\Api\V1\Admin\RoleAssignmentController;
use Illuminate\Support\Facades\Route;

// TDD §7.1: base path /api/v1; breaking changes ship as /api/v2 with v1
// maintained on a published deprecation timeline.
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/admin/users/{user}/roles', [RoleAssignmentController::class, 'store'])
            ->name('admin.users.roles.store');
    });
});
