<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AnnounceExternalLedger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Platform\Organization;
use App\Services\Navigation\NavigationRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Buchhaltungsberichte ohne lokale Buchhaltung (MVP-1126): Die Seiten bleiben
 * erreichbar, sagen aber, dass sie nur lokale Buchungen auswerten. Der Hinweis
 * gilt nur für diese Anfrage; das Layout zeigt `info` zentral an.
 */
final class AnnounceExternalLedger {
    public function handle(Request $request, Closure $next): Response {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if ($request->isMethod('GET')
            && $organization instanceof Organization
            && ! $request->session()->has('info')
            && ! app(NavigationRegistry::class)->condition('accounting.local_ledger', $request->user(), $organization)) {
            $request->session()->now('info', (string) __('accounting.reports.external_ledger_notice'));
        }

        return $next($request);
    }
}
