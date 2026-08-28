<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Legacy `issues` — a fault type belonging to a system. */
class Issue extends Model
{
    use SoftDeletes;

    protected $table = 'issues';

    protected $fillable = ['system_id', 'title', 'delete_reason'];

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class, 'system_id');
    }
}
