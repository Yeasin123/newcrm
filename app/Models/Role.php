<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Maps to the legacy `roles` table (shared with the old app).
 *
 * `permissions` is stored as a JSON-encoded array of free-text permission
 * strings, e.g. ["Dashboard","Customers","Sale Sheet"]. The `array` cast
 * handles encode/decode transparently — keep the stored shape identical.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property array<int, string> $permissions
 */
class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'user_id',
        'name',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
