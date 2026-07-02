<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // nullable: o perfil pode existir antes de o convite ser aceite
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('regime', 20)->default('HIBRIDO'); // DIA|NOITE|HIBRIDO
            $table->string('contract', 20)->default('H40');   // H37_30|H40
            $table->boolean('fixa_noite')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
