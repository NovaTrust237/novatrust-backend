<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grant_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('project_title');
            $table->string('category', 50);
            $table->text('description');
            $table->bigInteger('requested_amount_cfa');
            $table->string('full_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('id_document_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grant_applications');
    }
};
