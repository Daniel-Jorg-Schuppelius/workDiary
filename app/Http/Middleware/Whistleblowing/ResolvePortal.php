<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvePortal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Whistleblowing;

use App\Models\Whistleblowing\Portal;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Loest die Organisation ausschliesslich ueber den oeffentlichen Portal-Slug auf
 * (Abschnitt 6.2). Ein unbekanntes oder deaktiviertes Portal liefert 404 (keine
 * Information ueber Existenz). Bindet die Organisation an den Container, damit
 * tenant-scoped Modelle korrekt arbeiten – ganz ohne Auth-Kontext.
 */
class ResolvePortal {
    public function handle(Request $request, Closure $next): Response {
        $slug = (string) $request->route('portal');

        $portal = Portal::query()
            ->withoutGlobalScopes()
            ->where('public_slug', $slug)
            ->where('is_enabled', true)
            ->first();

        if (! $portal instanceof Portal) {
            abort(404);
        }

        // BEWUSST OHNE Mandantensperre (Sicherheitsscan 2026-08-23, S-42):
        // Karriereportal und B2B-Katalog eines suspendierten Mandanten gehen
        // zu — das Hinweisgeberportal nicht. Es erfüllt eine gesetzliche
        // Pflicht (HinSchG); ein Meldekanal, der wegen einer offenen Rechnung
        // schließt, schadet der hinweisgebenden Person und dem Betreiber. Wer
        // ihn abschalten will, deaktiviert das Portal (`is_enabled`).

        // Organisation an den Container binden (kein Auth-/Org-Context-Middleware).
        $org = $portal->organization;
        if ($org !== null) {
            app()->instance('currentOrganization', $org);
        }

        $request->attributes->set('wb_portal', $portal);
        $this->applyLocale($request, $portal);

        return $next($request);
    }

    /** Sprache des Portals: Auswahl der meldenden Person (nur aus den erlaubten), sonst die Standardsprache. */
    private function applyLocale(Request $request, Portal $portal): void {
        $offered = $portal->offeredLocales();
        $wanted = $request->query('lang');
        if (is_string($wanted) && in_array($wanted, $offered, true) && $request->hasSession()) {
            $request->session()->put('wb_locale', $wanted);
        }

        $chosen = $request->hasSession() ? $request->session()->get('wb_locale') : null;
        $locale = is_string($chosen) && in_array($chosen, $offered, true) ? $chosen : $offered[0];

        App::setLocale($locale);
        Carbon::setLocale(Locales::carbon($locale));
    }
}
