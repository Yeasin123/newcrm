<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Admin / staff account. Maps to the legacy `users` table (shared with the
 * old app) — do not add or rename columns here. `type = 1` means admin;
 * `role_id IS NULL` means superadmin (full permission bypass).
 *
 * @property int $id
 * @property string|null $role_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $contact
 * @property int $type
 * @property bool $login_off
 * @property int $status
 * @property Carbon|null $last_activity
 * @property string|null $remember_token
 * @property-read string $name
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'contact',
        'address',
        'city',
        'state',
        'zip_code',
        'picture',
        'ext_number',
        'v_salesman_id',
        'type',
        'role_id',
        'status',
        'login_off',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_activity' => 'datetime',
            'password' => 'hashed',
            'login_off' => 'boolean',
        ];
    }

    /**
     * The role this user belongs to. `role_id` is a varchar on the legacy
     * table; null means superadmin.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Superadmins have `role_id IS NULL` and bypass every permission check.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role_id === null || $this->role_id === '';
    }

    /**
     * `type = 1` is the admin/staff flag on the legacy schema.
     */
    public function isAdmin(): bool
    {
        return (int) $this->type === 1;
    }

    /**
     * True when the user's role grants the given free-text permission string,
     * or when the user is a superadmin.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $granted = $this->role?->permissions ?? [];

        return in_array($permission, $granted, true);
    }

    /**
     * Display name built from the legacy first/last name columns (there is no
     * `name` column). Flux UI reads `$user->name`.
     */
    public function name(): Attribute
    {
        return Attribute::get(
            fn (): string => trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: (string) $this->email,
        );
    }

    /**
     * Get the user's initials for avatars.
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
