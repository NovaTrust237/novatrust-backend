<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrantApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_title',
        'category',
        'description',
        'requested_amount_cfa',
        'full_name',
        'phone',
        'email',
        'id_document_path',
        'status',
        'admin_note',
    ];

    protected $casts = [
        'requested_amount_cfa' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
