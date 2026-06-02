<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'email',
        'amount_cfa',
        'duration_months',
        'interest_rate',
        'monthly_payment_cfa',
        'purpose',
        'id_document_path',
        'status',
        'admin_note',
    ];

    protected $casts = [
        'amount_cfa' => 'integer',
        'monthly_payment_cfa' => 'integer',
        'duration_months' => 'integer',
        'interest_rate' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
