<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Kommunikationsnotizen, Kommentare, externe Teilnehmer“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class CommunicationManifest extends Manifest {
    public function code(): string {
        return 'communication';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Kommunikationsnotizen, Kommentare, externe Teilnehmer';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Communication',
            'ExternalParticipant',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'comments',
            'communication_note_participants',
            'communication_notes',
            'external_participant_events',
            'external_participants',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Communication,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Communication\DeadlineScans\CommunicationFollowupScan::class,
            ],
            \App\Services\Search\Indexing\Sources\SearchSource::class => [
                \App\Services\Communication\Search\CommunicationNoteSource::class,
            ],
        ];
    }
}
