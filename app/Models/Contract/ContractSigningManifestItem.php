<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractSigningManifestItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contract;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gebundene Dokumentversion einer Vertragsfassung (Feature 157): Vertrag oder
 * Anlage, mit dem beim Einfrieren gemessenen SHA-256. Der FK auf
 * document_versions ist RESTRICT — ein gebundener Nachweis kann nicht per
 * Kaskade verschwinden; den Soft-Delete des Dokuments blockt der
 * DocumentService.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $revision_id
 * @property int $document_version_id
 * @property string $role
 * @property int $sort
 * @property string $original_name
 * @property string $sha256
 * @property int $size
 */
class ContractSigningManifestItem extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const ROLE_CONTRACT = 'contract';

    public const ROLE_ATTACHMENT = 'attachment';

    protected $fillable = [
        'organization_id', 'revision_id', 'document_version_id', 'role', 'sort', 'original_name', 'sha256', 'size',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sort' => 'integer',
        'size' => 'integer',
    ];

    /** @return BelongsTo<ContractSigningRevision, $this> */
    public function revision(): BelongsTo {
        return $this->belongsTo(ContractSigningRevision::class, 'revision_id');
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function documentVersion(): BelongsTo {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function isContract(): bool {
        return $this->role === self::ROLE_CONTRACT;
    }

    public function roleLabel(): string {
        return (string) __('contract-signing.manifest.role.' . $this->role);
    }
}
