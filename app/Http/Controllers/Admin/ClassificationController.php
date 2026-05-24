<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\Classification\ClassificationDomain;
use App\Exceptions\ClassificationValidationException;
use App\Http\Controllers\Controller;
use App\Models\{Classification, Organization};
use App\Services\Classification\ClassificationManager;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassificationController extends Controller {
    public function __construct(
        private readonly ClassificationManager $manager,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', Classification::class);

        $organization = $this->currentOrganization();

        $platformByDomain = Classification::query()
            ->whereNull('organization_id')
            ->orderBy('domain')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->groupBy(static fn (Classification $classification): string => $classification->domain->value);

        $orgByDomain = Classification::query()
            ->where('organization_id', $organization->id)
            ->orderBy('domain')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->groupBy(static fn (Classification $classification): string => $classification->domain->value);

        return view('admin.classifications.index', [
            'organization' => $organization,
            'domains' => ClassificationDomain::cases(),
            'domainLabels' => $this->domainLabels(),
            'platformByDomain' => $platformByDomain,
            'orgByDomain' => $orgByDomain,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', Classification::class);

        $sourceClassification = $this->resolveSourceClassification($request->integer('source'));
        $domain = $sourceClassification instanceof Classification
            ? $sourceClassification->domain
            : $this->resolveDomain($request->string('domain')->toString());

        return view('admin.classifications._form_dialog', [
            'classification' => new Classification([
                'domain' => $domain?->value,
                'active' => true,
                'sort_order' => 100,
            ]),
            'domains' => ClassificationDomain::cases(),
            'domainLabels' => $this->domainLabels(),
            'sourceClassification' => $sourceClassification,
        ]);
    }

    public function importForm(): View {
        Gate::authorize('create', Classification::class);

        return view('admin.classifications._import_dialog');
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Classification::class);

        $validated = $request->validate([
            'source_classification_id' => ['nullable', 'integer', 'exists:classifications,id'],
            'domain' => ['required_without:source_classification_id', 'string', Rule::in(array_map(static fn (ClassificationDomain $domain): string => $domain->value, ClassificationDomain::cases()))],
            'code' => ['required_without:source_classification_id', 'string', 'max:60'],
            'label' => ['required', 'string', 'max:180'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'color_hex' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $organization = $this->currentOrganization();
        $sourceClassification = $this->resolveSourceClassification($validated['source_classification_id'] ?? null);

        try {
            $payload = $this->payloadFromRequest($request, $validated, $sourceClassification instanceof Classification);

            if ($sourceClassification instanceof Classification) {
                $this->manager->overridePlatformDefault($organization->id, $sourceClassification, $payload);
            } else {
                $domain = ClassificationDomain::from((string) $validated['domain']);
                $this->manager->createForOrganization($organization->id, $domain, $payload);
            }
        } catch (ClassificationValidationException $exception) {
            return $this->redirectWithManagerError($exception);
        }

        return redirect()->route('admin.classifications.index')
            ->with('success', __('Klassifikation wurde gespeichert.'));
    }

    public function edit(Classification $classification): View {
        Gate::authorize('update', $classification);
        $this->ensureOrganizationScoped($classification);

        return view('admin.classifications._form_dialog', [
            'classification' => $classification,
            'domains' => ClassificationDomain::cases(),
            'domainLabels' => $this->domainLabels(),
            'sourceClassification' => null,
        ]);
    }

    public function update(Request $request, Classification $classification): RedirectResponse {
        Gate::authorize('update', $classification);
        $this->ensureOrganizationScoped($classification);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:180'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'color_hex' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        try {
            $this->manager->update($classification, $this->payloadFromRequest($request, $validated, true));
        } catch (ClassificationValidationException $exception) {
            return $this->redirectWithManagerError($exception);
        }

        return redirect()->route('admin.classifications.index')
            ->with('success', __('Klassifikation wurde aktualisiert.'));
    }

    public function import(Request $request): RedirectResponse {
        Gate::authorize('create', Classification::class);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'max:'.(int) setting('uploads.csv_import_kb', 10240)],
        ]);

        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            return back()->with('error', __('Keine CSV-Datei hochgeladen.'));
        }

        try {
            $rows = $this->parseImportFile($file);
            $result = $this->manager->importCsv($this->currentOrganization()->id, $rows);
        } catch (ClassificationValidationException $exception) {
            return redirect()->route('admin.classifications.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.classifications.index')
            ->with('success', __('CSV-Import: :created angelegt, :updated aktualisiert.', [
                'created' => $result['created'],
                'updated' => $result['updated'],
            ]));
    }

    public function reorder(Request $request, string $domain): RedirectResponse {
        Gate::authorize('create', Classification::class);

        $domainEnum = ClassificationDomain::tryFrom($domain);
        abort_unless($domainEnum instanceof ClassificationDomain, 404);

        $validated = $request->validate([
            'sort_map' => ['required', 'array'],
            'sort_map.*' => ['integer', 'min:0', 'max:100000'],
        ]);

        $organization = $this->currentOrganization();
        $submitted = [];

        foreach ($validated['sort_map'] as $id => $sortOrder) {
            $submitted[] = [
                'id' => (int) $id,
                'sort_order' => (int) $sortOrder,
            ];
        }

        usort($submitted, static function (array $left, array $right): int {
            $bySort = $left['sort_order'] <=> $right['sort_order'];

            return $bySort !== 0 ? $bySort : ($left['id'] <=> $right['id']);
        });

        $orderedIds = Classification::query()
            ->where('organization_id', $organization->id)
            ->where('domain', $domainEnum->value)
            ->whereIn('id', array_column($submitted, 'id'))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $knownIds = array_flip($orderedIds);
        $orderedIds = [];
        foreach ($submitted as $row) {
            if (isset($knownIds[$row['id']])) {
                $orderedIds[] = $row['id'];
            }
        }

        if ($orderedIds === []) {
            return redirect()->route('admin.classifications.index')
                ->with('error', __('Keine gültigen Klassifikationen zum Umordnen übergeben.'));
        }

        $this->manager->reorder($organization->id, $domainEnum, $orderedIds);

        return redirect()->route('admin.classifications.index')
            ->with('success', __('Reihenfolge für :domain wurde gespeichert.', ['domain' => $this->domainLabels()[$domainEnum->value] ?? $domainEnum->value]));
    }

    public function destroy(Classification $classification): RedirectResponse {
        Gate::authorize('delete', $classification);
        $this->ensureKnownClassification($classification);

        try {
            $this->manager->delete($classification);
        } catch (ClassificationValidationException $exception) {
            return redirect()->route('admin.classifications.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.classifications.index')
            ->with('success', __('Klassifikation wurde gelöscht.'));
    }

    public function deactivateDefault(Classification $classification): RedirectResponse {
        Gate::authorize('deactivateDefault', Classification::class);
        abort_unless($classification->isPlatformDefault(), 404);

        $organization = $this->currentOrganization();
        $this->manager->deactivatePlatformDefaultForOrganization($organization->id, $classification);

        return redirect()->route('admin.classifications.index')
            ->with('success', __('Plattform-Default wurde für diese Organisation deaktiviert.'));
    }

    private function currentOrganization(): Organization {
        abort_unless(app()->bound('currentOrganization'), 403);

        $organization = app('currentOrganization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }

    private function ensureKnownClassification(Classification $classification): void {
        $organization = $this->currentOrganization();

        if ($classification->organization_id !== null && $classification->organization_id !== $organization->id) {
            abort(403);
        }
    }

    private function ensureOrganizationScoped(Classification $classification): void {
        $organization = $this->currentOrganization();
        abort_unless($classification->organization_id === $organization->id, 403);
    }

    private function resolveSourceClassification(?int $classificationId): ?Classification {
        if ($classificationId === null || $classificationId === 0) {
            return null;
        }

        $classification = Classification::query()->findOrFail($classificationId);
        abort_unless($classification->isPlatformDefault(), 404);

        return $classification;
    }

    private function resolveDomain(?string $value): ?ClassificationDomain {
        if ($value === null || $value === '') {
            return null;
        }

        return ClassificationDomain::tryFrom($value);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payloadFromRequest(Request $request, array $validated, bool $skipCode): array {
        $payload = [
            'label' => (string) $validated['label'],
            'sort_order' => (int) ($validated['sort_order'] ?? 100),
            'color_hex' => $this->nullableString($validated['color_hex'] ?? null),
            'icon' => $this->nullableString($validated['icon'] ?? null),
            'description' => $this->nullableString($validated['description'] ?? null),
            'active' => $request->boolean('active'),
        ];

        if (! $skipCode) {
            $payload['code'] = (string) $validated['code'];
        }

        return $payload;
    }

    private function nullableString(mixed $value): ?string {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function redirectWithManagerError(ClassificationValidationException $exception): RedirectResponse {
        $field = match ($exception->errorCode) {
            ClassificationValidationException::CODE_INVALID_CODE,
            ClassificationValidationException::CODE_DUPLICATE => 'code',
            ClassificationValidationException::CODE_INVALID_LABEL => 'label',
            ClassificationValidationException::CODE_INVALID_COLOR => 'color_hex',
            default => 'classification',
        };

        return back()
            ->withInput()
            ->withErrors([$field => $exception->getMessage()]);
    }

    /**
     * @return list<array{domain?: string, code?: string, label?: string, sort_order?: int|string|null, color_hex?: string|null, icon?: string|null}>
     */
    private function parseImportFile(UploadedFile $file): array {
        $path = $file->getRealPath();
        if (! is_string($path) || $path === '') {
            throw ClassificationValidationException::importInvalid(0, 'CSV-Datei konnte nicht gelesen werden.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ClassificationValidationException::importInvalid(0, 'CSV-Datei konnte nicht geöffnet werden.');
        }

        try {
            $firstLine = fgets($handle);
            if (! is_string($firstLine)) {
                throw ClassificationValidationException::importInvalid(1, 'CSV-Datei ist leer.');
            }

            $delimiter = $this->detectDelimiter($firstLine);
            $headers = array_map(
                fn (string $header): string => $this->normalizeHeader($header),
                $this->parseCsvLine($firstLine, $delimiter),
            );

            foreach (['domain', 'code', 'label'] as $requiredHeader) {
                if (! in_array($requiredHeader, $headers, true)) {
                    throw ClassificationValidationException::importInvalid(1, "Pflichtspalte '{$requiredHeader}' fehlt.");
                }
            }

            $rows = [];
            $lineNumber = 1;
            while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
                $lineNumber++;

                if (count($line) === 1 && $line[0] === null) {
                    continue;
                }

                $row = [];
                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $value = isset($line[$index]) ? trim((string) $line[$index]) : null;
                    $row[$header] = $value === '' ? null : $value;
                }

                if ($row === [] || $this->isEmptyImportRow($row)) {
                    continue;
                }

                $importRow = [
                    'domain' => (string) ($row['domain'] ?? ''),
                    'code' => (string) ($row['code'] ?? ''),
                    'label' => (string) ($row['label'] ?? ''),
                ];

                if (array_key_exists('sort_order', $row)) {
                    $importRow['sort_order'] = $row['sort_order'];
                }
                if (array_key_exists('color_hex', $row)) {
                    $importRow['color_hex'] = $row['color_hex'];
                }
                if (array_key_exists('icon', $row)) {
                    $importRow['icon'] = $row['icon'];
                }

                $rows[] = $importRow;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function parseCsvLine(string $line, string $delimiter): array {
        $parsed = str_getcsv(rtrim($line, "\r\n"), $delimiter);

        return array_map(static fn ($value): string => is_string($value) ? $value : '', $parsed);
    }

    private function detectDelimiter(string $line): string {
        $delimiters = [';', ',', "\t"];
        $best = ';';
        $bestCount = 0;

        foreach ($delimiters as $delimiter) {
            $count = count(str_getcsv($line, $delimiter));
            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function normalizeHeader(string $header): string {
        $normalized = strtolower(trim($header));
        $normalized = ltrim($normalized, "\xEF\xBB\xBF");

        return str_replace([' ', '-'], '_', $normalized);
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function isEmptyImportRow(array $row): bool {
        foreach ($row as $value) {
            if ($value !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function domainLabels(): array {
        return [
            ClassificationDomain::EntryType->value => __('Auftragstypen'),
            ClassificationDomain::Activity->value => __('Tätigkeiten'),
            ClassificationDomain::DefectType->value => __('Fehlertypen'),
            ClassificationDomain::RootCause->value => __('Ursachen'),
            ClassificationDomain::Result->value => __('Ergebnisse'),
            ClassificationDomain::Priority->value => __('Prioritäten'),
            ClassificationDomain::GoodwillReason->value => __('Kulanzgründe'),
            ClassificationDomain::ReworkReason->value => __('Nacharbeitsgründe'),
            ClassificationDomain::ProductGroup->value => __('Produktgruppen'),
            ClassificationDomain::DienstmittelType->value => __('Dienstmitteltypen'),
        ];
    }
}
