<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequestItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bestellbare Katalog-Einheit (Feature 065, MVP-154): Formular über das
 * 032-Vorlagensystem, Genehmigungskette, Fulfillment-Adapter, versioniert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $service_offering_id
 * @property string $name
 * @property string|null $description
 * @property int|null $form_template_id
 * @property array<int, array<string, mixed>>|null $approval_chain
 * @property int|null $sla_contract_id
 * @property string $fulfillment
 * @property array<string, mixed>|null $fulfillment_config
 * @property int $version
 * @property array<string, mixed>|null $visibility
 * @property bool $active
 */
class RequestItem extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    /** @var array<string, mixed> */
    protected $attributes = ['active' => true, 'version' => 1, 'fulfillment' => 'task'];

    protected $fillable = [
        'organization_id', 'service_offering_id', 'name', 'description',
        'form_template_id', 'approval_chain', 'sla_contract_id',
        'fulfillment', 'fulfillment_config', 'version', 'visibility', 'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'approval_chain' => 'array',
        'fulfillment_config' => 'array',
        'visibility' => 'array',
        'active' => 'boolean',
        'version' => 'integer',
    ];

    /** @return BelongsTo<ServiceOffering, $this> */
    public function offering(): BelongsTo {
        return $this->belongsTo(ServiceOffering::class, 'service_offering_id');
    }

    /** @return BelongsTo<FormTemplate, $this> */
    public function formTemplate(): BelongsTo {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }
}
