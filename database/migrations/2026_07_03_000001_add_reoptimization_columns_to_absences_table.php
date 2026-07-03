<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suporte a re-otimização parcial (issue #18): guarda a escala PUBLISHED
 * afetada por uma ausência, os buracos de cobertura calculados no momento do
 * registo (H1, comparado com coverage_rules) e o resultado da última
 * re-otimização pedida ao solver para essa ausência.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->json('coverage_gaps')->nullable()->after('note');
            $table->timestamp('reoptimized_at')->nullable()->after('coverage_gaps');
            $table->string('reoptimization_status', 20)->nullable()->after('reoptimized_at'); // FEASIBLE|INFEASIBLE|UNAVAILABLE
            $table->json('reoptimization_conflicts')->nullable()->after('reoptimization_status');
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_id');
            $table->dropColumn(['coverage_gaps', 'reoptimized_at', 'reoptimization_status', 'reoptimization_conflicts']);
        });
    }
};
