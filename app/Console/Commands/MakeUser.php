<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Provision or update an admin/staff account on the shared `users` table.
 * Superadmins are created by leaving --role blank (legacy `role_id IS NULL`).
 */
class MakeUser extends Command
{
    protected $signature = 'app:make-user
        {--email= : Email address}
        {--first= : First name}
        {--last= : Last name}
        {--password= : Plaintext password (prompted if omitted)}
        {--role= : roles.id to attach; omit for superadmin}';

    protected $description = 'Create or update an admin user in the livedata users table';

    public function handle(): int
    {
        $email = $this->option('email') ?: text('Email', required: true);
        $first = $this->option('first') ?: text('First name', default: 'Admin');
        $last = $this->option('last') ?: text('Last name', default: '');
        $plain = $this->option('password') ?: password('Password', required: true);
        $roleId = $this->option('role') ?: null;

        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $existed = $user->exists;

        $user->fill([
            'first_name' => $first,
            'last_name' => $last ?: null,
            'password' => Hash::make($plain),
            'type' => 1,
            'role_id' => $roleId,
            'status' => 1,
            'login_off' => false,
        ]);
        $user->deleted_at = null;
        $user->save();

        $this->info(sprintf(
            '%s admin user #%d <%s> (%s).',
            $existed ? 'Updated' : 'Created',
            $user->id,
            $user->email,
            $roleId ? "role {$roleId}" : 'superadmin',
        ));

        return self::SUCCESS;
    }
}
