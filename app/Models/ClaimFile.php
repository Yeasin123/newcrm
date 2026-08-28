<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `claim_files` — an uploaded attachment path for a claim. */
class ClaimFile extends Model
{
    protected $table = 'claim_files';

    protected $fillable = ['claim_id', 'upload_file'];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class, 'claim_id');
    }
}
