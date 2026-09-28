<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleAssignmentController extends Controller
{
    public function store(Request $request, User $user, AuditLogger $auditLogger): JsonResponse
    {
        $this->authorize('assign', [RoleAssignment::class, $user]);

        $data = $request->validate([
            'role' => ['required', 'in:buyer,seller,shipper,admin,sub_admin'],
            'admin_role_id' => ['nullable', 'required_if:role,sub_admin', 'exists:admin_roles,id'],
        ]);

        $assignment = DB::transaction(function () use ($user, $data, $request, $auditLogger) {
            $assignment = $user->assignRole($data['role'], $data['admin_role_id'] ?? null);

            $auditLogger->log(
                actor: $request->user(),
                action: 'role_assignment.created',
                subject: $user,
                before: null,
                after: $assignment->only(['role', 'scope']),
                ip: $request->ip(),
            );

            return $assignment;
        });

        return response()->json(['data' => $assignment], 201);
    }
}
