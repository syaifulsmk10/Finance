<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('submission_id')->constrained('submissions')->onDelete('cascade');
            $table->enum('status', ['approved', 'denied', 'pending'])->default('pending');
            $table->text('notes')->nullable();
            $table->boolean('is_checked')->default(false); // Untuk ceklis dokumen fisik
            $table->dateTime('checked_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_approvals');
    }
};
