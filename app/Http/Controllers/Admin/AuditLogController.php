<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visualizador do audit_log da organização (PRD F10): quem fez o quê, quando,
 * e um resumo legível do diff. Só-leitura; os registos nascem em AuditLog::record().
 */
class AuditLogController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $action = $request->string('action')->toString() ?: null;

        $logs = AuditLog::query()
            ->with('actor:id,name')
            ->when($action, fn ($query) => $query->where('action', $action))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->toIso8601String(),
                'actor_name' => $log->actor?->name ?? 'Sistema',
                'action' => $log->action,
                'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
                'subject_id' => $log->subject_id,
                'changes_summary' => $log->changes ? json_encode($log->changes, JSON_UNESCAPED_UNICODE) : null,
            ]);

        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return Inertia::render('admin/audit/index', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => ['action' => $action],
        ]);
    }
}
