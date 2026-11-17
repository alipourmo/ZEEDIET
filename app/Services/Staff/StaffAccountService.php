<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Staff account management by the admin (spec sections 7.6 and 8.2): create, update, deactivate, reset password.
 *
 * Passwords are never written to the audit log. A new or reset password is generated here, returned once to the
 * caller to show on screen, and stored only as a hash.
 */
final class StaffAccountService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: User, 1: string} The new account and its one-time password.
     */
    public function create(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor): array {
            $password = $this->temporaryPassword();

            $user = User::create($data + [
                'password' => $password,
                'must_change_password' => true,
            ]);

            $this->audit->log(AuditAction::Create, $user, null, ['new' => $this->summary($user)], $actor);

            return [$user, $password];
        });
    }

    /**
     * Deactivating a user also deletes their sessions, so they are signed out at once, not on their next request.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $user->fill($data);
            $dirty = $user->getDirty();

            if ($dirty === []) {
                return $user;
            }

            $old = array_intersect_key($user->getOriginal(), $dirty);
            $user->save();

            if (array_key_exists('is_active', $dirty) && ! $user->is_active) {
                $this->endSessions($user);
            }

            $this->audit->log(AuditAction::Update, $user, null, ['old' => $old, 'new' => $dirty], $actor);

            return $user;
        });
    }

    /**
     * The user must change the password at the next login, and their sessions end now.
     *
     * @return string The new password, to be shown once.
     */
    public function resetPassword(User $user, User $actor): string
    {
        return DB::transaction(function () use ($user, $actor): string {
            $password = $this->temporaryPassword();

            $user->forceFill([
                'password' => $password,
                'must_change_password' => true,
            ])->save();

            $this->endSessions($user);

            $this->audit->log(AuditAction::PasswordReset, $user, null, null, $actor);

            return $password;
        });
    }

    private function endSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
    }

    private function temporaryPassword(): string
    {
        // 16 letters and digits: long enough for the 10-character minimum, and easy to read out.
        return Str::random(16);
    }

    /**
     * The fields worth keeping in the audit trail. Never the password.
     *
     * @return array<string, mixed>
     */
    private function summary(User $user): array
    {
        return [
            'name' => $user->name,
            'mobile' => $user->mobile,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ];
    }
}
