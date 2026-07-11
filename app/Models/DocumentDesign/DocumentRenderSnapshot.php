<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentRenderSnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\DocumentDesign;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Beim Finalisieren eingefrorener Renderstand (MVP-300): Profilversion,
 * Layout-/Block-/Tabellenregeln, Asset-Hashes und Generatorversion. Spätere
 * Profiländerungen verändern finalisierte Dokumente nicht — der Renderer
 * verwendet ausschließlich das Snapshot-Payload.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $document_render_profile_id
 * @property int|null $profile_version_id
 * @property RenderDocumentKind $document_kind
 * @property string $documentable_type
 * @property int $documentable_id
 * @property array<string, mixed> $payload
 * @property string|null $first_asset_sha256
 * @property string|null $following_asset_sha256
 * @property string $generator_version
 * @property int|null $created_by
 */
class DocumentRenderSnapshot extends Model {
    use BelongsToOrganization;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'document_render_profile_id',
        'profile_version_id',
        'document_kind',
        'documentable_type',
        'documentable_id',
        'payload',
        'first_asset_sha256',
        'following_asset_sha256',
        'generator_version',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'document_kind' => RenderDocumentKind::class,
        'payload' => 'array',
    ];

    protected static function booted(): void {
        // Snapshots sind Nachweise: nachträgliche Änderungen sind verboten.
        static::updating(function (): void {
            throw new \RuntimeException('Render-Snapshots sind unveränderlich.');
        });
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo {
        return $this->morphTo();
    }
}
