<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphMailScopes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Services;

use App\Models\Mail\EmailConnection;
use App\Plugins\Msgraph\Models\MsgraphMailConnection;
use App\Plugins\Msgraph\MsgraphPlugin;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * Rechte des Mail-Eingangs über Graph (MVP-1141). Die Anmeldung fordert sie
 * nur an, wenn die Organisation M365-Postfächer führt; reine
 * Versand-Organisationen bekommen keine Leserechte. `Mail.ReadWrite.Shared`
 * deckt laut Microsoft das eigene und freigegebene Postfächer ab.
 */
final class MsgraphMailScopes {
    public const READ_WRITE = 'Mail.ReadWrite';

    public const READ_WRITE_SHARED = 'Mail.ReadWrite.Shared';

    /** @return list<string> */
    public static function intakeScopesFor(?int $organizationId): array {
        if ($organizationId === null) {
            return [];
        }
        $addresses = EmailConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('transport', MsgraphPlugin::MAIL_TRANSPORT)
            ->pluck('mailbox_address');
        if ($addresses->isEmpty()) {
            return [];
        }

        return $addresses->contains(static fn (?string $address): bool => trim((string) $address) !== '')
            ? [self::READ_WRITE, self::READ_WRITE_SHARED]
            : [self::READ_WRITE];
    }

    /** Fehlendes Recht für das Postfach; null, wenn erteilt oder nicht bekannt. */
    public static function missingFor(MsgraphMailConnection $mail, EmailConnection $connection): ?string {
        $granted = array_map(
            static fn (string $scope): string => mb_strtolower((string) substr($scope, (int) strrpos('/' . $scope, '/'))),
            StringHelper::words((string) $mail->scopes),
        );
        if ($granted === []) {
            return null;
        }
        $shared = trim((string) $connection->mailbox_address) !== '';
        $accepted = $shared ? [self::READ_WRITE_SHARED] : [self::READ_WRITE, self::READ_WRITE_SHARED];
        foreach ($accepted as $scope) {
            if (in_array(mb_strtolower($scope), $granted, true)) {
                return null;
            }
        }

        return $accepted[0];
    }
}
