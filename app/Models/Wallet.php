<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Wallet extends Model
{
use HasFactory;


protected $fillable = ['user_id','balance_cfa','virtual_balance_cfa','investment_principal_cfa'];


public function user() { return $this->belongsTo(User::class); }
}
