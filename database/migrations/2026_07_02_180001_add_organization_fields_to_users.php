<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('EMPLOYEE')->after('email');
            // feed iCal privado (F9); regenerável
            $table->string('calendar_token', 64)->nullable()->unique()->after('remember_token');
            // preferências de notificação por tipo de evento (F7)
            $table->json('notification_prefs')->nullable()->after('calendar_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['role', 'calendar_token', 'notification_prefs']);
        });
    }
};
