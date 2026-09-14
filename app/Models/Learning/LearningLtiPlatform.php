<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiPlatform.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;

/**
 * Eine fremde Plattform, die WorkDiary als LTI-Tool startet (Feature 149).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $issuer
 * @property string $client_id
 * @property string $lookup_hash
 * @property list<string> $deployment_ids
 * @property string $authorization_endpoint
 * @property string $jwks_url
 * @property bool $is_active
 */
class LearningLtiPlatform extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'issuer',
        'client_id',
        'deployment_ids',
        'authorization_endpoint',
        'jwks_url',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deployment_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public static function lookupHash(string $issuer, string $clientId): string {
        return CryptoHelper::hash($issuer . "\n" . $clientId);
    }

    protected static function booted(): void {
        static::saving(static function (self $platform): void {
            $platform->lookup_hash = self::lookupHash($platform->issuer, $platform->client_id);
        });
    }
}
