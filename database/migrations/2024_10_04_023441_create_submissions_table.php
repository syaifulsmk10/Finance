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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->onDelete('cascade'); 
            $table->enum('type', ['Reimburesent', 'Payment Process']);
            $table->string('purpose');
            $table->dateTime('submission_date');
            $table->bigInteger('amount')->nullable();
            $table->dateTime('due_date');
            $table->text('description')->nullable();
            $table->enum('finish_status', ['approved', 'denied', 'process'])->default('process');;
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
