<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldAutosaveController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Fields;

use App\Enums\Modules\ModuleKind;
use App\Exceptions\FieldDocumentConflictException;
use App\Http\Controllers\Controller;
use App\Models\Contracts\AutosavesFieldDocument;
use App\Modules\ModuleRegistry;
use App\Services\Fields\FieldAutosaver;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\{MorphMap, Sqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Gemeinsamer Endpunkt „Speichern je Feld“ (Feature 203, MVP-1650):
 * `PATCH field-documents/{type}/{document}/fields/{field}` mit `value`
 * und/oder `note` und der Version, die der Browser zuletzt bekam.
 *
 * Der Träger wird über seinen Morph-Alias gefunden und muss
 * {@see AutosavesFieldDocument} erfüllen; die Organisation begrenzt der
 * globale Scope des Modells, das Modul seiner Tabelle muss aktiv sein, und
 * seine Policy entscheidet über `autosave`. Antwort: neue Version und Stand
 * je Abschnitt (200), Hinweis bei Versionskonflikt oder abgeschlossenem
 * Träger (409), Prüffehler des Felds (422).
 */
final class FieldAutosaveController extends Controller {
    public function __construct(
        private readonly FieldAutosaver $autosaver,
        private readonly ModuleRegistry $modules,
        private readonly FeatureFlagResolver $features,
    ) {}

    public function __invoke(Request $request, string $type, string $document, string $field): JsonResponse {
        $model = $this->resolveDocument($type, $document);
        Gate::authorize('autosave', $model);

        $data = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'note' => ['sometimes', 'nullable', 'string', 'max:' . FieldAutosaver::NOTE_MAX],
        ]);
        $input = [];
        if ($request->exists('value')) {
            $input['value'] = $request->input('value');
        }
        if ($request->exists('note')) {
            $input['note'] = $data['note'] ?? null;
        }

        try {
            $result = $this->autosaver->save($model, $field, $input, (int) $data['version']);
        } catch (FieldDocumentConflictException $e) {
            return response()->json($e->toArray(), 409);
        }

        return response()->json($result->toArray());
    }

    /**
     * Träger zum Alias auflösen — nur Modelle mit dem Vertrag, nur Aliase der
     * Morph-Map (kein Klassenname aus der URL), nur aktive Module.
     *
     * @return Model&AutosavesFieldDocument
     */
    private function resolveDocument(string $type, string $document): Model {
        $class = MorphMap::aliases()[$type] ?? null;
        if ($class === null || ! is_subclass_of($class, AutosavesFieldDocument::class)) {
            abort(404);
        }

        $manifest = $this->modules->byTable((new $class)->getTable());
        $license = $manifest !== null && $manifest->kind() === ModuleKind::Feature ? $manifest->licenseCode() : null;
        if ($license !== null && ! $this->features->isEnabled($license)) {
            abort(423);
        }

        $id = Sqid::decodeOrNumeric($class, $document);
        /** @var (Model&AutosavesFieldDocument)|null $model */
        $model = $id !== null && $id > 0 ? $class::query()->find($id) : null;

        return $model ?? abort(404);
    }
}
