<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentRenderProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\DocumentDesign;

use App\Enums\DocumentDesign\{RenderDocumentKind, RenderProfileStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Renderprofil (MVP-300): fachlicher Name, zugeordnete Dokumentarten und die
 * aktive, unveränderliche Profilversion. Ein org-weites Standardprofil
 * (`is_default`) greift als Fallback; ein dokumentartspezifisches Profil mit
 * höherer Priorität übersteuert es.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property RenderProfileStatus $status
 * @property bool $is_default
 * @property array<int, string>|null $document_kinds
 * @property string|null $locale
 * @property int $priority
 * @property int|null $active_version_id
 */
class DocumentRenderProfile extends Model {
    use Auditable;

    use BelongsToOrganization;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'status',
        'is_default',
        'is_customer_specific',
        'document_kinds',
        'document_family',
        'locale',
        'priority',
        'active_version_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => RenderProfileStatus::class,
        'is_default' => 'boolean',
        'is_customer_specific' => 'boolean',
        'document_kinds' => 'array',
        'document_family' => \App\Enums\DocumentDesign\RenderDocumentFamily::class,
        'priority' => 'integer',
    ];

    /** @return HasMany<DocumentRenderProfileVersion, $this> */
    public function versions(): HasMany {
        return $this->hasMany(DocumentRenderProfileVersion::class, 'document_render_profile_id');
    }

    /** @return BelongsTo<DocumentRenderProfileVersion, $this> */
    public function activeVersion(): BelongsTo {
        return $this->belongsTo(DocumentRenderProfileVersion::class, 'active_version_id');
    }

    public function coversKind(RenderDocumentKind $kind): bool {
        return in_array($kind->value, $this->document_kinds ?? [], true);
    }

    /** Familien-Variante (#83): deckt das Profil die Familie der Art ab? */
    public function coversFamily(RenderDocumentKind $kind): bool {
        return $this->document_family === $kind->family();
    }
}
