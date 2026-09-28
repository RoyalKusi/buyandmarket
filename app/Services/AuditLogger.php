<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * TDD §8.9: every privileged mutation writes an immutable audit_logs row.
 * Call this from inside the same DB transaction as the mutation it records
 * — never as an afterthought queued job, so a rolled-back mutation can never
 * leave an orphaned audit entry.
 */
class AuditLogger
{
    public function log(
        User|string $actor,
        string $action,
        Model $subject,
        ?array $before = null,
        ?array $after = null,
        ?string $ip = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actor instanceof User ? $actor->id : null,
            'actor_type' => $actor instanceof User ? 'user' : $actor,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'before' => $before,
            'after' => $after,
            'ip' => $ip,
            'created_at' => now(),
        ]);
    }
}
