<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('target_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('requester_assignment_id')->constrained('shift_assignments')->cascadeOnDelete();
            $table->foreignId('target_assignment_id')->constrained('shift_assignments')->cascadeOnDelete();
            $table->string('status', 20)->default('PENDING');
            // resultado da validação do solver no momento do pedido (explicabilidade)
            $table->json('validation')->nullable();
            // snapshot da config da org no momento do pedido
            $table->boolean('admin_approval_required')->default(false);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('vacation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('PENDING');
            $table->json('impact')->nullable(); // análise do solver (vacation-impact)
            $table->text('note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('type', 20)->default('SICK'); // SICK|UNJUSTIFIED|OTHER
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absences');
        Schema::dropIfExists('vacation_requests');
        Schema::dropIfExists('swap_requests');
    }
};
