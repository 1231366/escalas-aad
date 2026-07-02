<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('DRAFT'); // DRAFT|PUBLISHED|ARCHIVED
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users');
            $table->timestamp('published_at')->nullable();
            $table->json('solver_stats')->nullable(); // objetivo, wall time, conflitos
            $table->timestamps();

            $table->unique(['organization_id', 'period_start', 'period_end']);
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // null = folga (F) — a grelha completa fica explícita e auditável
            $table->foreignId('shift_type_id')->nullable()->constrained();
            $table->string('origin', 20)->default('GENERATED'); // GENERATED|SWAP|MANUAL|VACATION
            $table->timestamps();

            $table->unique(['schedule_id', 'employee_id', 'date']);
            $table->index(['schedule_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('schedules');
    }
};
