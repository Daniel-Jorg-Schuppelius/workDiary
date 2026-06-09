<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingMailboxService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Models\Whistleblowing\WhistleblowingCase;
use Illuminate\Support\Carbon;
use SensitiveParameter;

/**
 * Anonymes Postfach (Abschnitt 7.2 / 25). Login ausschliesslich ueber das
 * hochentropische Geheimnis – der Fallcode ist NIE Eingabe. Auf einen Miss wird
 * trotzdem ein Argon2-Verify (Dummy) ausgefuehrt, damit das Timing keinen
 * Rueckschluss auf die Existenz eines Falls erlaubt.
 */
class WhistleblowingMailboxService {
    public function __construct(private readonly ReporterCredentialService $credentials) {}

    /** Liefert den Fall fuer ein gueltiges Geheimnis, sonst null. */
    public function authenticate(#[SensitiveParameter] string $secret): ?WhistleblowingCase {
        $lookup = $this->credentials->lookupHmac($secret);

        /** @var WhistleblowingCase|null $case */
        $case = WhistleblowingCase::withoutGlobalScopes()
            ->where('access_code_lookup', $lookup)
            ->first();

        if ($case === null) {
            $this->credentials->performDummyVerify(); // konstantes Timing
            return null;
        }

        if (! $this->credentials->verifySecret($secret, (string) $case->getAttribute('access_code_hash'))) {
            return null;
        }

        return $case;
    }

    /** Markiert an den Reporter freigegebene Bearbeiter-Nachrichten als gelesen. */
    public function markHandlerMessagesRead(WhistleblowingCase $case): void {
        $case->messages()
            ->where('visibility', 'reporter')
            ->where('author_type', 'handler')
            ->whereNull('read_by_reporter_at')
            ->update(['read_by_reporter_at' => Carbon::now()]);
    }
}
