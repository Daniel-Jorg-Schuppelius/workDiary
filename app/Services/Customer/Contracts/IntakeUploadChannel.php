<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeUploadChannel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Contracts;

use App\Models\Customer\{CustomerIntake, CustomerIntakeUploadLink};
use App\Models\Platform\Organization;
use App\Services\Customer\Dto\{IntakeRemoteFile, IntakeUploadLinkData};
use Carbon\CarbonImmutable;
use Psr\Http\Message\StreamInterface;
use SensitiveParameter;

/**
 * Erweiterungspunkt Upload-Kanal des Kundeneingangs (MVP-1078): ein Plugin
 * stellt einen Upload-Link ohne Konto bereit (Nextcloud-Dateiablage) und
 * liefert die dort hochgeladenen Dateien. Plugins tragen sich über
 * `ModuleRegistry::contribute()` ein. Prüfung, Ablage und Journal übernimmt
 * {@see \App\Services\Customer\Intake\CustomerIntakeUploadChannels}.
 */
interface IntakeUploadChannel {
    /** Stabiler Schlüssel (Spalte `channel`). */
    public function key(): string;

    public function label(): string;

    /** Plugin aktiv und für die Organisation eingerichtet? */
    public function isAvailable(Organization $organization): bool;

    /** Laufzeit eines neuen Links in Tagen. */
    public function linkLifetimeDays(Organization $organization): int;

    /** Legt Ordner und Freigabe „nur hochladen" mit Passwort und Ablauf an. */
    public function createLink(CustomerIntake $intake, #[SensitiveParameter] string $password, CarbonImmutable $expiresAt): IntakeUploadLinkData;

    /** Widerruft die Freigabe; eine bereits fehlende gilt als widerrufen. */
    public function revokeLink(CustomerIntakeUploadLink $link): void;

    /** @return list<IntakeRemoteFile> Dateien im Ordner des Links */
    public function listFiles(CustomerIntakeUploadLink $link): array;

    public function download(CustomerIntakeUploadLink $link, IntakeRemoteFile $file): StreamInterface;
}
