<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StreamsRegisterExport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms\Concerns;

use App\Models\Isms\IsmsScope;
use App\Models\User;
use App\Services\Isms\RegisterExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Direkt-Export der ISMS-Register (?format=json|csv): Gate wie die
 * Listenseite, Format-Whitelist (sonst 404), Download-Stream mit
 * Register-Dateinamen. Nutzt die exports-Property des Controllers
 * (Refactoring Welle 3, B10).
 */
trait StreamsRegisterExport {
    /**
     * @param  class-string  $policyModel  Model-Klasse für viewAny
     * @param  callable(): array{columns: array<string, string>, rows: array<int, array<string, scalar|null>>}  $register
     */
    private function streamRegisterExport(Request $request, string $policyModel, string $registerKey, callable $register, ?IsmsScope $scope = null): StreamedResponse {
        Gate::authorize('viewAny', $policyModel);

        $format = (string) $request->query('format', 'json');
        abort_unless(in_array($format, RegisterExportService::FORMATS, true), 404);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $register();

        $content = $format === 'csv'
            ? $this->exports->toCsv($registerKey, $actor, $scope, $data)
            : $this->exports->toJson($registerKey, $actor, $scope, $data);

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $this->exports->filename($registerKey, $format), [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/json; charset=UTF-8',
        ]);
    }
}
