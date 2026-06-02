<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Activation extends Model
{
use HasFactory;
protected $fillable = ['user_id','amount_cfa','provider','provider_tx','reference','status','paid_at'];


public function user() { return $this->belongsTo(User::class); }
}
