<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditProgramController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Isms;

use App\Http\Controllers\Controller;
use App\Models\Isms\{IsmsAudit, IsmsAuditProgram, IsmsScope};
use App\Models\User;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Mehrjähriges Auditprogramm (Nachtrag 044d): Programme je Geltungsbereich
 * mit Zyklus/Normbezug; Einzel-Audits werden zugeordnet und je Programmjahr
 * gruppiert angezeigt (Nachweis über Feststellungen/Auditpakete der Audits).
 * Rechte folgen dem Audit-Muster (IsmsAudit-Policy).
 */
class AuditProgramController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', IsmsAudit::class);

        return view('isms.audit-programs.index', [
            'programs' => IsmsAuditProgram::query()
                ->with(['scope', 'audits'])
                ->orderByDesc('starts_on')
                ->paginate(25),
            'scopes' => IsmsScope::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'unassignedAudits' => IsmsAudit::query()
                ->whereNull('isms_audit_program_id')
                ->orderByDesc('planned_on')
                ->limit(50)
                ->get(),
            'canManage' => Gate::allows('create', IsmsAudit::class),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsAudit::class);

        /** @var User $actor */
        $actor = Auth::user();

        // Sqid-Input vor der Validierung dekodieren (numerischer Fallback für Alt-Clients).
        if ($request->filled('isms_scope_id')) {
            $request->merge(['isms_scope_id' => Sqid::decodeOrNumeric(IsmsScope::class, $request->input('isms_scope_id'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:180'],
            'isms_scope_id' => [
                'required', 'integer',
                Rule::exists('isms_scopes', 'id')->where('organization_id', $actor->organization_id),
            ],
            'norm' => ['nullable', 'string', 'max:64'],
            'edition' => ['nullable', 'string', 'max:16'],
            'cycle_years' => ['required', 'integer', 'min:1', 'max:6'],
            'starts_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $program = IsmsAuditProgram::query()->create([
            'organization_id' => $actor->organization_id,
            'status' => 'active',
            ...$data,
        ]);
        $program->audit('isms.audit_program.created', ['name' => $program->name]);

        return redirect()->route('isms.audit-programs.index')
            ->with('success', __('Auditprogramm angelegt.'));
    }

    /** Status ändern (active/completed/cancelled) oder Audit zuordnen. */
    public function update(Request $request, IsmsAuditProgram $program): RedirectResponse {
        Gate::authorize('create', IsmsAudit::class);

        /** @var User $actor */
        $actor = Auth::user();

        if ($request->filled('attach_audit_id')) {
            $request->merge(['attach_audit_id' => Sqid::decodeOrNumeric(IsmsAudit::class, $request->input('attach_audit_id'))]);
        }

        $data = $request->validate([
            'status' => ['nullable', 'in:active,completed,cancelled'],
            'attach_audit_id' => [
                'nullable', 'integer',
                Rule::exists('isms_audits', 'id')->where('organization_id', $actor->organization_id),
            ],
        ]);

        if (($data['status'] ?? null) !== null) {
            $program->update(['status' => $data['status']]);
            $program->audit('isms.audit_program.status_changed', ['status' => $data['status']]);
        }

        if (($data['attach_audit_id'] ?? null) !== null) {
            IsmsAudit::query()->whereKey((int) $data['attach_audit_id'])
                ->update(['isms_audit_program_id' => $program->id]);
            $program->audit('isms.audit_program.audit_attached', ['audit_id' => (int) $data['attach_audit_id']]);
        }

        return redirect()->route('isms.audit-programs.index')
            ->with('success', __('Auditprogramm aktualisiert.'));
    }

    public function destroy(IsmsAuditProgram $program): RedirectResponse {
        Gate::authorize('create', IsmsAudit::class);

        $program->audit('isms.audit_program.deleted', ['name' => $program->name]);
        $program->delete(); // SoftDelete; Audits bleiben (FK nullOnDelete greift erst bei forceDelete)

        return redirect()->route('isms.audit-programs.index')
            ->with('success', __('Auditprogramm gelöscht.'));
    }
}
