<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Auth\SsoProtocol;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SSO-Anbindung einer Organisation (Feature 057): genau eine Verbindung je
 * Protokoll (OIDC MVP-120, SAML MVP-121). Der Client-Secret liegt encrypted
 * at-rest und ist über $hidden von Serialisierung und Audit ausgeschlossen.
 * `enforced` sperrt den lokalen Passwort-Login der Organisation serverseitig
 * (Break-Glass: users.sso_exempt).
 *
 * @property int $id
 * @property int $organization_id
 * @property SsoProtocol $protocol
 * @property string $label
 * @property bool $active
 * @property bool $enforced
 * @property bool $allow_email_link
 * @property string|null $issuer
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property string|null $scopes
 * @property string|null $idp_entity_id
 * @property string|null $idp_sso_url
 * @property string|null $idp_certificate
 * @property string|null $idp_certificate_next
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $last_login_at
 */
class SsoConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    public const DEFAULT_OIDC_SCOPES = 'openid profile email';

    protected $fillable = [
        'organization_id',
        'protocol',
        'label',
        'active',
        'enforced',
        'allow_email_link',
        'allow_private_network',
        'issuer',
        'client_id',
        'client_secret',
        'scopes',
        'idp_entity_id',
        'idp_sso_url',
        'idp_certificate',
        'idp_certificate_next',
        'created_by',
    ];

    protected $hidden = ['client_secret'];

    protected $casts = [
        'protocol' => SsoProtocol::class,
        'active' => 'boolean',
        'enforced' => 'boolean',
        'allow_email_link' => 'boolean',
        'allow_private_network' => 'boolean',
        'client_secret' => 'encrypted',
        'last_login_at' => 'datetime',
    ];

    /** @return HasMany<SsoIdentity, $this> */
    public function identities(): HasMany {
        return $this->hasMany(SsoIdentity::class);
    }

    public function isOidc(): bool {
        return $this->protocol === SsoProtocol::Oidc;
    }

    public function isSaml(): bool {
        return $this->protocol === SsoProtocol::Saml;
    }

    /** OIDC-Scopes als Liste; `openid` ist immer enthalten. */
    public function scopeList(): string {
        $scopes = trim((string) ($this->scopes ?: self::DEFAULT_OIDC_SCOPES));

        return str_contains(" $scopes ", ' openid ') ? $scopes : "openid $scopes";
    }

    /**
     * IdP-Signaturzertifikate (aktuell + optional Rotations-Nachfolger) —
     * beide werden bei der SAML-Validierung akzeptiert (x509certMulti).
     *
     * @return list<string>
     */
    public function idpCertificates(): array {
        return array_values(array_filter([
            trim((string) $this->idp_certificate) ?: null,
            trim((string) $this->idp_certificate_next) ?: null,
        ]));
    }

    /**
     * Aktive, erzwungene Verbindung einer Organisation — der zentrale Check
     * für die Passwort-Login-Sperre. Bewusst ohne Global Scopes, weil er im
     * Gast-Kontext (Login) läuft, in dem keine currentOrganization gebunden ist.
     */
    public static function enforcementActiveFor(?int $organizationId): bool {
        if ($organizationId === null) {
            return false;
        }

        return static::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->where('enforced', true)
            ->exists();
    }
}
