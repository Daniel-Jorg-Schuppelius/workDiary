<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnsureMailboxSession.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware\Whistleblowing;

use App\Models\Whistleblowing\WhistleblowingCase;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schuetzt die Postfach-Routen: setzt voraus, dass eine gueltige, NICHT
 * abgelaufene Postfachsitzung (serverseitig, ueber Cookie) besteht. Kein Token
 * im URL-Pfad (Abschnitt 25). Verlaengert die Sitzung gleitend bei Aktivitaet.
 */
class EnsureMailboxSession {
    public function handle(Request $request, Closure $next): Response {
        $session = $request->session();
        $caseId = $session->get('wb_mailbox_case_id');
        $expiresAt = $session->get('wb_mailbox_expires_at');

        $expired = ! is_string($expiresAt) || Carbon::parse($expiresAt)->isPast();

        if (! $caseId || $expired) {
            $this->forget($request);

            return redirect()->route('whistleblowing.mailbox.login');
        }

        /** @var WhistleblowingCase|null $case */
        $case = WhistleblowingCase::withoutGlobalScopes()->find($caseId);
        if ($case === null) {
            $this->forget($request);

            return redirect()->route('whistleblowing.mailbox.login');
        }

        // Org binden, damit tenant-scoped Modelle korrekt arbeiten.
        $org = $case->organization;
        if ($org !== null) {
            app()->instance('currentOrganization', $org);
        }

        $request->attributes->set('wb_mailbox_case', $case);

        // Gleitende Verlaengerung.
        $session->put('wb_mailbox_expires_at', Carbon::now()
            ->addMinutes((int) config('whistleblowing.mailbox_session_minutes', 30))
            ->toIso8601String());

        return $next($request);
    }

    private function forget(Request $request): void {
        $request->session()->forget(['wb_mailbox_case_id', 'wb_mailbox_expires_at']);
    }
}
