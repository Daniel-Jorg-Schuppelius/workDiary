<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Import\{ImportEntity, ImportRunState};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCsvImportJob;
use App\Models\{AuditLog, ImportRun, ImportRunError, User};
use App\Services\Import\CsvPreflightAnalyzer;
use App\Support\Toolkit\CsvFacade;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Storage};

/**
 * MVP-049 — CSV-Import-Wizard (Admin).
 *
 * Workflow: index → create (entity) → preflight (POST upload) → show
 * (Vorschau + Fehler) → confirm (Dispatch Job) → show (Status).
 */
class ImportController extends Controller {
    use ResolvesCurrentOrganization;

    private const ALLOWED_SORTS = ['id', 'entity', 'input_filename', 'state', 'rows_total', 'created_at'];

    public function __construct(private readonly CsvPreflightAnalyzer $analyzer) {}

    public function index(Request $request): View {
        $organization = $this->currentOrganization();

        $filters = [
            'entity' => $request->string('entity')->toString(),
            'state' => $request->string('state')->toString(),
        ];

        // Whitelist-Auflösung zentral (C21; Vollaudit 2026-07, N26) — bei
        // ungültigem Key fallen Key UND Richtung auf die Defaults zurück.
        [$sort, $dir] = \App\Support\SortableQuery::resolve($request, self::ALLOWED_SORTS, 'id');

        $runs = ImportRun::query()
            ->where('organization_id', $organization->id)
            ->when($filters['entity'] !== '', fn($q) => $q->where('entity', $filters['entity']))
            ->when($filters['state'] !== '', fn($q) => $q->where('state', $filters['state']))
            ->orderBy($sort, $dir)
            ->paginate(25)
            ->withQueryString();

        return view('admin.imports.index', [
            'runs' => $runs,
            'filters' => $filters,
            'entities' => ImportEntity::cases(),
            'states' => ImportRunState::cases(),
            'organization' => $organization,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Request $request): View {
        $organization = $this->currentOrganization();
        $entity = ImportEntity::tryFrom($request->string('entity', 'customers')->toString()) ?? ImportEntity::Customers;
        $this->authorizeImport($entity);

        $supportsInboxFirst = app(\App\Services\Import\EntitySpecRegistry::class)->for($entity)
            instanceof \App\Services\Import\InboxFirstSpec;

        return view('admin.imports.create', [
            'organization' => $organization,
            'entity' => $entity,
            'entities' => ImportEntity::cases(),
            'supportsInboxFirst' => $supportsInboxFirst,
        ]);
    }

    /**
     * CSV-Mustervorlage je Entität (Feature 020 MVP; Vollaudit 2026-07, N8):
     * Header direkt aus Spec::columns() plus eine Beispielzeile, die
     * Pflichtspalten (Spec::requiredColumns()) markiert. Semikolon-Delimiter —
     * der Preflight erkennt das Trennzeichen ohnehin automatisch.
     */
    public function template(string $entity): Response {
        $entityEnum = ImportEntity::tryFrom($entity);
        abort_if($entityEnum === null, 404);
        $this->authorizeImport($entityEnum);

        $spec = app(\App\Services\Import\EntitySpecRegistry::class)->for($entityEnum);
        $columns = $spec->columns();
        $required = $spec->requiredColumns();

        $example = array_map(
            static fn(string $column): string => in_array($column, $required, true)
                ? (string) __('import.template.example_required')
                : (string) __('import.template.example_optional'),
            $columns,
        );

        $csv = CsvFacade::buildCsv($columns, [array_combine($columns, $example)]);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="import-vorlage-' . $entityEnum->value . '.csv"',
        ]);
    }

    /**
     * MVP-438: iCal-Beispieldatei für die Zeiterfassungs-Importe. Zeigt ein
     * terminiertes VEVENT mit `ORGANIZER`-E-Mail (User-Auflösung) und `UID`
     * (Idempotenz) — die Regeln, auf die der {@see \App\Services\Import\Source\IcalImportSource}
     * aufsetzt.
     */
    public function icalSample(string $entity): Response {
        $entityEnum = ImportEntity::tryFrom($entity);
        abort_if($entityEnum === null, 404);
        abort_unless(in_array($entityEnum, [ImportEntity::Attendances, ImportEntity::ProjectTimes], true), 404);
        $this->authorizeImport($entityEnum);

        $summary = $entityEnum === ImportEntity::Attendances ? 'Arbeitszeit Baustelle' : 'Projekt Website Relaunch';
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//workDiary//Zeiterfassungs-Import//DE',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:beispiel-1@workdiary',
            'DTSTAMP:20260701T060000Z',
            'DTSTART:20260701T060000Z',
            'DTEND:20260701T140000Z',
            'SUMMARY:' . $summary,
            'DESCRIPTION:' . __('Beispiel — bitte durch echte Kalenderdaten ersetzen.'),
            'ORGANIZER:mailto:mitarbeiter@example.com',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="import-beispiel-' . $entityEnum->value . '.ics"',
        ]);
    }

    public function preflight(Request $request): RedirectResponse {
        $organization = $this->currentOrganization();
        $data = $request->validate([
            'entity' => ['required', \Illuminate\Validation\Rule::enum(ImportEntity::class)],
            // A13: neben CSV auch Excel (.xlsx); MVP-438: zusätzlich iCal (.ics) —
            // gleiches Größenlimit, ein Wizard-Pfad.
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,ics', 'max:' . (CsvPreflightAnalyzer::MAX_BYTES / 1024)],
            'match_policy' => ['nullable', 'in:auto_create,inbox_first'],
            // MVP-438: optionale iCal-Kategorie-Allowlist (nur Events dieser
            // Kategorien werden als Anwesenheit gewertet).
            'ical_category_allowlist' => ['nullable', 'string', 'max:500'],
        ]);
        $entity = ImportEntity::from($data['entity']);
        $this->authorizeImport($entity);

        $options = [];
        $allowlist = $this->parseCategoryAllowlist($data['ical_category_allowlist'] ?? null);
        if ($allowlist !== []) {
            $options['category_allowlist'] = $allowlist;
        }

        $run = $this->analyzer->analyze(
            $request->file('file'),
            $entity,
            $organization,
            Auth::user(),
            (string) ($data['match_policy'] ?? 'auto_create'),
            $options,
        );

        if ($run->state === ImportRunState::Failed) {
            AuditLog::create([
                'organization_id' => $organization->id,
                'user_id' => Auth::id(),
                'event' => 'import.preflightFailed',
                'auditable_type' => ImportRun::class,
                'auditable_id' => $run->id,
                'changes' => ['entity' => $entity->value],
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        }

        return redirect()->route('admin.imports.show', $run);
    }

    public function show(Request $request, ImportRun $import): View {
        $this->ensureOwned($import);

        $errors = $import->errors()
            ->orderBy('row_number')
            ->orderBy('id')
            ->paginate(50, ['*'], 'errors_page')
            ->withQueryString();

        // Rang 58: Auswahloptionen fürs Wert-Mapping-Formular — Ziel hängt an
        // der offenen Spalte (user_email → Benutzer, sonst Tag/Klassifikation).
        $hasPending = $import->unresolved_values !== null && $import->unresolved_values !== [];
        $pendingColumn = $hasPending ? array_key_first((array) $import->unresolved_values) : null;
        $isUserMapping = $pendingColumn === 'user_email';

        $tagOptions = $hasPending && ! $isUserMapping
            ? \App\Models\Tag::query()->where('organization_id', $import->organization_id)->orderBy('name')->get(['id', 'name'])
            : collect();
        // Für x-user-select: Sqid als Wert, Name + E-Mail als Label (die
        // Adresse ist hier das Unterscheidungsmerkmal).
        $userOptions = $isUserMapping
            ? \App\Models\User::query()->where('organization_id', $import->organization_id)->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn (\App\Models\User $u): array => ['sqid' => $u->sqid, 'label' => $u->name . ' (' . $u->email . ')'])
                ->all()
            : [];

        // A13: Klassifikations-Auswahl (effektiver Katalog je Domäne) — nur für
        // Entitäten, deren Zielmodell Klassifikationen trägt.
        $classificationOptions = [];
        if ($hasPending && ! $isUserMapping && $import->entity->supportsClassifications()) {
            $resolver = app(\App\Services\Classification\ClassificationResolver::class);
            foreach (\App\Enums\Classification\ClassificationDomain::cases() as $domain) {
                $list = $resolver->list($import->organization_id, $domain);
                if ($list->isNotEmpty()) {
                    $classificationOptions[$domain->value] = $list;
                }
            }
        }

        return view('admin.imports.show', [
            'run' => $import,
            'errors' => $errors,
            'tagOptions' => $tagOptions,
            'userOptions' => $userOptions,
            'classificationOptions' => $classificationOptions,
        ]);
    }

    /**
     * Wert-Mapping (Rang 58, A13): unbekannte Tag-/Kategorie-Quellwerte
     * zuordnen — bestehendes Tag, neues Tag, Klassifikation (nur Entitäten
     * mit Klassifikations-Trägerschaft) oder ignorieren. Persistiert je Org +
     * Entität (import_value_mappings), Wiederholimporte lösen automatisch auf.
     */
    public function mapping(Request $request, ImportRun $import): RedirectResponse {
        $this->ensureOwned($import);
        $this->authorizeImport($import->entity);

        $data = $request->validate([
            'mappings' => ['required', 'array'],
            'mappings.*.value' => ['required', 'string', 'max:191'],
            'mappings.*.action' => ['required', 'in:tag,new,ignore,classification,user'],
            'mappings.*.tag_id' => ['nullable', 'string', 'max:32'],
            'mappings.*.classification_id' => ['nullable', 'string', 'max:32'],
            'mappings.*.user_id' => ['nullable', 'string', 'max:32'],
        ]);

        $pendingColumn = array_key_first((array) $import->unresolved_values) ?? 'tags';
        $pending = collect((array) (($import->unresolved_values ?? [])[$pendingColumn] ?? []));
        // Zeitimporte: die user_email-Spalte kennt nur Benutzer-Zuordnung oder
        // Überspringen — Tag-/Klassifikations-Ziele sind dort sinnlos.
        $isUserMapping = $pendingColumn === 'user_email';

        foreach ($data['mappings'] as $entry) {
            $value = (string) $entry['value'];
            $normalized = \App\Models\ImportValueMapping::normalize($value);
            if (! $pending->contains(fn (string $p): bool => \App\Models\ImportValueMapping::normalize($p) === $normalized)) {
                continue; // nur offene Werte dieses Laufs
            }
            if ($isUserMapping && ! in_array($entry['action'], ['user', 'ignore'], true)) {
                continue;
            }

            $tagId = null;
            $classificationId = null;
            $userId = null;
            $kind = \App\Models\ImportValueMapping::KIND_IGNORE;
            if ($entry['action'] === 'user') {
                if (! $isUserMapping) {
                    continue;
                }
                $user = \App\Models\User::query()
                    ->where('organization_id', $import->organization_id)
                    ->whereKey(\App\Support\Sqid::decodeOrNumeric(\App\Models\User::class, $entry['user_id'] ?? null) ?? 0)
                    ->first();
                if ($user === null) {
                    continue;
                }
                $userId = $user->id;
                $kind = \App\Models\ImportValueMapping::KIND_USER;
            } elseif ($entry['action'] === 'new') {
                $tagId = \App\Models\Tag::findOrCreateByName($value, is_int(Auth::id()) ? Auth::id() : null)->id;
                $kind = \App\Models\ImportValueMapping::KIND_TAG;
            } elseif ($entry['action'] === 'tag') {
                $tag = \App\Models\Tag::query()
                    ->where('organization_id', $import->organization_id)
                    ->whereKey(\App\Support\Sqid::decodeOrNumeric(\App\Models\Tag::class, $entry['tag_id'] ?? null) ?? 0)
                    ->first();
                if ($tag === null) {
                    continue;
                }
                $tagId = $tag->id;
                $kind = \App\Models\ImportValueMapping::KIND_TAG;
            } elseif ($entry['action'] === 'classification') {
                // A13: nur für Entitäten, deren Zielmodell Klassifikationen
                // trägt; org-gescoped (eigene + Plattform-Defaults, aktiv).
                if (! $import->entity->supportsClassifications()) {
                    continue;
                }
                $classification = \App\Models\Classification::query()
                    ->whereKey(\App\Support\Sqid::decodeOrNumeric(\App\Models\Classification::class, $entry['classification_id'] ?? null) ?? 0)
                    ->where('active', true)
                    ->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $import->organization_id))
                    ->first();
                if ($classification === null) {
                    continue;
                }
                $classificationId = $classification->id;
                $kind = \App\Models\ImportValueMapping::KIND_CLASSIFICATION;
            }

            \App\Models\ImportValueMapping::query()->updateOrCreate(
                [
                    'organization_id' => $import->organization_id,
                    'entity' => $import->entity->value,
                    'source_value' => $normalized,
                ],
                ['target_kind' => $kind, 'tag_id' => $tagId, 'classification_id' => $classificationId, 'user_id' => $userId],
            );

            $pending = $pending->reject(fn (string $p): bool => \App\Models\ImportValueMapping::normalize($p) === $normalized)->values();
        }

        $import->unresolved_values = $pending->isEmpty() ? null : [$pendingColumn => $pending->all()];
        $import->save();

        return redirect()->route('admin.imports.show', $import)
            ->with('success', __('Wert-Zuordnung gespeichert.'));
    }

    public function confirm(Request $request, ImportRun $import): RedirectResponse {
        $this->ensureOwned($import);
        $this->authorizeImport($import->entity);
        abort_unless($import->state === ImportRunState::AwaitingApproval, 409);
        // Rang 58: erst bestätigen, wenn alle offenen Quellwerte zugeordnet sind.
        if ($import->unresolved_values !== null && $import->unresolved_values !== []) {
            return redirect()->route('admin.imports.show', $import)
                ->with('error', __('Bitte zuerst die unbekannten Quellwerte zuordnen.'));
        }

        AuditLog::create([
            'organization_id' => $import->organization_id,
            'user_id' => Auth::id(),
            'event' => 'import.confirmed',
            'auditable_type' => ImportRun::class,
            'auditable_id' => $import->id,
            'changes' => [
                'entity' => $import->entity->value,
                'rows_total' => $import->rows_total,
            ],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        ProcessCsvImportJob::dispatch($import->id);

        return redirect()->route('admin.imports.show', $import)
            ->with('success', __('Import wurde gestartet.'));
    }

    public function destroy(Request $request, ImportRun $import): RedirectResponse {
        $this->ensureOwned($import);
        abort_unless($import->state === ImportRunState::AwaitingApproval || $import->state === ImportRunState::Failed, 409);

        if ($import->storage_path !== '' && Storage::disk(CsvPreflightAnalyzer::DISK)->exists($import->storage_path)) {
            Storage::disk(CsvPreflightAnalyzer::DISK)->delete($import->storage_path);
        }
        $import->delete();

        return redirect()->route('admin.imports.index')
            ->with('success', __('Import wurde verworfen.'));
    }

    public function downloadErrors(Request $request, ImportRun $import): Response {
        $this->ensureOwned($import);

        $rows = [];
        foreach ($import->errors()->orderBy('row_number')->orderBy('id')->cursor() as $err) {
            /** @var ImportRunError $err */
            $rows[] = [
                'row' => $err->row_number,
                'field' => $err->field ?? '',
                'code' => $err->code->value,
                'message' => $err->message,
            ];
        }

        $csv = CsvFacade::buildCsv(['row', 'field', 'code', 'message'], $rows, ';');
        $filename = sprintf('errors_%d.csv', $import->id);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Zerlegt die iCal-Kategorie-Allowlist (Komma/Semikolon/Zeilenumbruch) in
     * eine deduplizierte Liste; Deckel bei 50 Einträgen (MVP-438).
     *
     * @return list<string>
     */
    private function parseCategoryAllowlist(?string $raw): array {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[,;\n\r]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $value = trim($part);
            if ($value !== '' && ! in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return array_slice($out, 0, 50);
    }

    private function ensureOwned(ImportRun $run): void {
        abort_unless($run->organization_id === $this->currentOrganization()->id, 403);
    }

    private function authorizeImport(ImportEntity $entity): void {
        $user = Auth::user();
        abort_unless(
            $user instanceof User && ($user->isAdmin() || $user->hasEffectivePermission($entity->permission())),
            403
        );
    }
}
