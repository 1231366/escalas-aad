<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 4);   // M | T | N
            $table->string('name');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->decimal('hours', 4, 2)->default(8);
            $table->string('color', 20)->default('#888888');
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('coverage_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0=segunda … 6=domingo (ISO)
            $table->unsignedSmallInteger('required');
            $table->timestamps();

            $table->unique(['organization_id', 'shift_type_id', 'weekday']);
        });

        Schema::create('rule_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value');
            $table->timestamps();

            $table->unique(['organization_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_configs');
        Schema::dropIfExists('coverage_rules');
        Schema::dropIfExists('shift_types');
    }
};
