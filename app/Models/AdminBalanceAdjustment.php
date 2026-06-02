<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminBalanceAdjustment extends Model
{
    use HasFactory;

    protected $fillable = ['admin_id', 'user_id', 'delta_cfa', 'reason'];

    protected $casts = [
        'delta_cfa' => 'integer',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
