<?php

namespace App\Models\RealEstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A brokerage / company an agent belongs to. Legacy `companies`. */
class Company extends Model
{
    protected $table = 'companies';

    protected $fillable = ['name', 'image', 'type', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'company_id');
    }
}
