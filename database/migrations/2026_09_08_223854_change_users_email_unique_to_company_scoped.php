<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove unique global de email e phone
            $table->dropUnique(['email']);
            $table->dropUnique(['phone']);

            // Adiciona unique composto: mesmo email/phone pode existir em empresas distintas
            $table->unique(['email', 'company_id'], 'users_email_company_unique');
            $table->unique(['phone', 'company_id'], 'users_phone_company_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_company_unique');
            $table->dropUnique('users_phone_company_unique');
            $table->unique('email');
            $table->unique('phone');
        });
    }
};
