<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Protokolle“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ProtocolManifest extends Manifest {
    public function code(): string {
        return 'protocol';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Protokolle';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Protocol',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'protocol_events',
            'protocol_item_photos',
            'protocol_items',
            'protocol_signature_tokens',
            'protocol_signatures',
            'protocols',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Protocols,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Plugins\Support\Mirror\Contracts\MirrorPdfRenderer::class => [
                \App\Services\Protocol\Mirror\ProtocolMirrorPdf::class,
            ],
            \App\Services\Search\Indexing\Sources\SearchSource::class => [
                \App\Services\Protocol\Search\ProtocolSource::class,
            ],
            \App\Services\Fields\Contracts\FieldExtension::class => [
                \App\Services\Protocol\Fields\Extensions\DefectField::class,
                \App\Services\Protocol\Fields\Extensions\MeasurementSeriesField::class,
                \App\Services\Protocol\Fields\Extensions\AttachmentsField::class,
                \App\Services\Protocol\Fields\Extensions\SignatureField::class,
            ],
        ];
    }
}
