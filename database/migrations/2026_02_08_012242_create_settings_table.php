<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('company_name');
            $table->string('cnpj', 14)->nullable();
            $table->string('email');
            $table->string('phone_one', 20);
            $table->string('phone_two', 20)->nullable();
            $table->string('phone_three', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('number', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
