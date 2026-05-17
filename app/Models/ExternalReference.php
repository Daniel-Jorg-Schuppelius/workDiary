<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalReference.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $plugin_id
 * @property string $external_type
 * @property string $referenceable_type
 * @property int $referenceable_id
 * @property string $external_id
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $synced_at
 */
class ExternalReference extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'plugin_id',
        'external_type',
        'referenceable_type',
        'referenceable_id',
        'external_id',
        'payload',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
