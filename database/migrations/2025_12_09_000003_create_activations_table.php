<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
public function up()
{
Schema::create('activations', function (Blueprint $table) {
$table->id();
$table->foreignId('user_id')->constrained()->onDelete('cascade');
$table->bigInteger('amount_cfa')->default(20000);
$table->string('provider')->nullable();
$table->string('provider_tx')->nullable();
$table->string('reference')->nullable();
$table->enum('status', ['pending','paid','failed'])->default('pending');
$table->timestamp('paid_at')->nullable();
$table->timestamps();
});
}


public function down()
{
Schema::dropIfExists('activations');
}
};
