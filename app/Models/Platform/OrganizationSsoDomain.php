<?php
/*
 * Created on   : Fri Aug 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationSsoDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verifizierte E-Mail-Domain einer Organisation für die SSO-Login-Discovery
 * (Feature 057-Ausbau). Eine Domain gehört global zu genau einer Organisation
 * (Unique-Constraint) — daraus leitet der Login aus der E-Mail-Adresse die
 * passende SSO-Organisation ab. Bewusst OHNE Org-Global-Scope, da die
 * Discovery im Gast-Kontext (unauthentifiziert) läuft.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $domain
 * @property int|null $created_by
 */
class OrganizationSsoDomain extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'domain',
        'created_by',
        'verification_token',
        'verified_at',
        'verification_checked_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'verified_at' => 'datetime',
        'verification_checked_at' => 'datetime',
    ];

    /** Name des DNS-TXT-Eintrags, mit dem eine Domain nachgewiesen wird. */
    public const DNS_PREFIX = '_workdiary-sso';

    /**
     * Nur eine nachgewiesene Domain lenkt Anmeldungen (Sicherheitsscan
     * 2026-08-23, S-49). Ohne Nachweis konnte ein Mandant die Mail-Domain
     * eines anderen beanspruchen und dessen Nutzer auf den eigenen IdP
     * leiten — bei aktivem JIT-Provisioning samt Kontoanlage dort.
     */
    public function isVerified(): bool {
        return $this->verified_at !== null;
    }

    /** Vollständiger DNS-Name, unter dem der Nachweis erwartet wird. */
    public function dnsRecordName(): string {
        return self::DNS_PREFIX . '.' . $this->domain;
    }

    /** Normalisiert eine Domain/E-Mail auf die kleingeschriebene Domain (ohne führendes @). */
    public static function normalize(string $value): string {
        $value = strtolower(trim($value));
        if (str_contains($value, '@')) {
            $value = (string) substr(strrchr($value, '@') ?: '@', 1);
        }

        return ltrim($value, '@');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }
}
