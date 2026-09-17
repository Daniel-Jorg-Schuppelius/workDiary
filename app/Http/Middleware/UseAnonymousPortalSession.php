<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UseAnonymousPortalSession.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eigenes Sitzungs-Cookie für die anonymen Portale (Meldeportal,
 * Betroffenenportal, Karriereportal).
 *
 * Ohne diese Trennung liefen Portal und Anwendung in DERSELBEN Sitzung: Wer
 * als Mitarbeiter angemeldet war und im selben Browser eine Meldung abgab,
 * hinterließ Fall-Nummer bzw. Postfach-Geheimnis in genau der Sitzungszeile,
 * die über `login_web_*` und die Spalte `user_id` auf ihn zeigte — wer
 * Datenbank und Schlüssel hat, konnte den Hinweisgeber benennen
 * (Sicherheitsaudit 2026-09-17, privacy-wb-1; HinSchG).
 *
 * Muss VOR `StartSession` laufen: der Store wird dort mit dem dann gültigen
 * Cookie-Namen gebaut. Nach der Antwort — die Sitzung ist zu diesem Zeitpunkt
 * bereits gespeichert und das Cookie gesetzt — wird beides zurückgestellt.
 */
class UseAnonymousPortalSession {
    /** Suffix am regulären Cookie-Namen; eigener Name ⇒ eigene Sitzungszeile. */
    public const SUFFIX = '_portal';

    public function __construct(private readonly SessionManager $manager) {}

    public function handle(Request $request, Closure $next): Response {
        $session = $this->manager->driver();
        $original = $session->getName();
        if ($original === '' || str_ends_with($original, self::SUFFIX)) {
            return $next($request);
        }

        // Nur der Name der Sitzung wird umgestellt: `StartSession` liest die
        // Kennung danach aus DIESEM Cookie und legt die Antwort-Kennung ebenso
        // ab — Portal und Anwendung teilen sich also keine Sitzungszeile mehr.
        $session->setName($original . self::SUFFIX);

        try {
            return $next($request);
        } finally {
            $session->setName($original);
        }
    }
}
