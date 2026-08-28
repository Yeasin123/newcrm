<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy `renew_agrement_pdfs` (keep the misspelled table name). Renewal agreement + signature. */
class RenewAgrementPdf extends Model
{
    protected $table = 'renew_agrement_pdfs';

    protected $fillable = [
        'client_id', 'signature', 'initial_pdf', 'updated_pdf', 'multi_agreement_pdf',
        'vip_pdf', 'signature_date_time', 'choose_pdf',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
