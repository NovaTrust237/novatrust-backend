<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
public function up()
{
Schema::create('transactions', function (Blueprint $table) {
$table->id();
$table->foreignId('user_id')->constrained()->onDelete('cascade');
$table->string('type'); // deposit, activation, withdrawal, gain
$table->bigInteger('amount_cfa');
$table->json('meta')->nullable();
$table->timestamps();
});
}


public function down()
{
Schema::dropIfExists('transactions');
}
};
