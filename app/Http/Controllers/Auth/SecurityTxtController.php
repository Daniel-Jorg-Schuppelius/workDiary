<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityTxtController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * CVD-Meldekanal nach RFC 9116: /.well-known/security.txt (CRA-Welle 1,
 * WorkDiary-Architecture/security/cra-red-compliance-2026-07.md §5).
 * Ohne konfigurierten Kontakt (SECURITY_TXT_CONTACT) antwortet der
 * Endpunkt 404 — bewusst kein erfundener Default.
 *
 * Läuft seit dem CRA-Tabletop 2026-09-01 ohne Gruppen-Stack
 * ({@see routes/well-known.php}) und setzt seine Header deshalb selbst.
 */
class SecurityTxtController extends Controller {
    public function __invoke(): Response {
        $contact = config('security.txt.contact');
        abort_if(!is_string($contact) || trim($contact) === '', 404);

        // RFC 9116 verlangt Contact als URI — nackte Mail-Adressen bekommen
        // das mailto:-Schema, URLs/URIs (enthalten ":") bleiben unverändert.
        $contact = trim($contact);
        if (!str_contains($contact, ':')) {
            $contact = 'mailto:' . $contact;
        }

        $lines = [
            'Contact: ' . $contact,
            'Expires: ' . now()->addDays((int) config('security.txt.expires_days'))->toIso8601ZuluString(),
            'Preferred-Languages: ' . config('security.txt.preferred_languages'),
            'Canonical: ' . url('/.well-known/security.txt'),
        ];

        $policy = config('security.txt.policy');
        if (is_string($policy) && trim($policy) !== '') {
            $lines[] = 'Policy: ' . trim($policy);
        }

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            // Anders als bei SCIM ausdrücklich zwischenspeicherbar: das
            // Dokument ist öffentlich, trägt sein eigenes Verfallsdatum und
            // soll einen Ausfall der Anwendung überdauern können.
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
