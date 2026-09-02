<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_app_users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('email')->nullable()->unique()->after('name');
            $table->string('password')->nullable()->after('email');
            $table->string('cpf', 14)->nullable()->unique()->after('password');
            $table->timestamp('email_verified_at')->nullable()->after('cpf');
            $table->boolean('onboarding_completed')->default(false)->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_app_users', function (Blueprint $table) {
            $table->dropColumn(['name', 'email', 'password', 'cpf', 'email_verified_at', 'onboarding_completed']);
        });
    }
};
