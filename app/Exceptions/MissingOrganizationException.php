<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MissingOrganizationException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Throwable;

/**
 * Wird geworfen, wenn ein eingeloggter Benutzer ohne Organisations-Zuordnung
 * versucht, einen tenant-scoped Datensatz anzulegen.
 *
 * Die Ausnahme rendert sich selbst zu einer freundlichen Fehlerseite bzw.
 * einem Redirect mit Flash-Message, statt einen Stacktrace zu zeigen.
 */
class MissingOrganizationException extends RuntimeException {
    public function __construct(
        public readonly string $modelClass,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf(
            'Kann %s nicht anlegen: Ihr Benutzerkonto ist keiner Organisation zugeordnet.',
            $modelClass
        ), 0, $previous);
    }

    public function render(Request $request): \Symfony\Component\HttpFoundation\Response {
        $shortName = class_basename($this->modelClass);
        $userMessage = __(
            'Ihr Benutzerkonto ist keiner Organisation zugeordnet. Bitte legen Sie '
                . 'zunächst eine Organisation an und weisen Sie Ihr Konto dieser zu, '
                . 'bevor Sie weitere Daten erfassen.'
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $userMessage,
                'model' => $shortName,
                'action_url' => route('admin.organizations.index'),
            ], 409);
        }

        $user = Auth::user();
        $canManage = $user !== null
            && Gate::forUser($user)->allows('viewAny', \App\Models\Organization::class);

        // Admin: direkt zum Anlegen weiterleiten, mit Flash-Hinweis.
        if ($canManage) {
            return redirect()
                ->route('admin.organizations.index')
                ->with('error', $userMessage);
        }

        // Ansonsten: schlanke Fehlerseite (kein layouts.app, da Tenant-Kontext fehlt).
        return response()->view('errors.missing-organization', [
            'modelShortName' => $shortName,
            'userMessage' => $userMessage,
        ], 409);
    }
}
