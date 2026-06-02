<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
public function up()
{
Schema::create('wallets', function (Blueprint $table) {
$table->id();
$table->foreignId('user_id')->constrained()->onDelete('cascade');
$table->bigInteger('balance_cfa')->default(0); // real money credited
$table->bigInteger('virtual_balance_cfa')->default(0); // after gains
$table->timestamps();
});
}


public function down()
{
Schema::dropIfExists('wallets');
}
};
