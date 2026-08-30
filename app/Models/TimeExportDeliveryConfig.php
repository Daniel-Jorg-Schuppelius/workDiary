<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportDeliveryConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Automatische Export-Lieferung je Organisation × Export-Profil
 * (A21 · MVP-019): E-Mail-Versand an eine validierte Empfängerliste
 * und/oder SFTP-Upload beim Export-Abschluss. Das SFTP-Passwort liegt
 * at-rest verschlüsselt (`encrypted`-Cast, APP_KEY), erscheint nie in
 * Views/Audit-Payloads ($hidden) und wird bei leerer Eingabe als NULL
 * gespeichert (leere encrypted-Strings crashen decrypt).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $profile
 * @property bool $mail_enabled
 * @property array<int, string>|null $mail_recipients
 * @property bool $sftp_enabled
 * @property string|null $sftp_host
 * @property int $sftp_port
 * @property string|null $sftp_username
 * @property string|null $sftp_password
 * @property string|null $sftp_root
 * @property string|null $sftp_host_fingerprint
 */
class TimeExportDeliveryConfig extends Model {
    use Auditable;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'profile',
        'mail_enabled',
        'mail_recipients',
        'sftp_enabled',
        'sftp_host',
        'sftp_port',
        'sftp_username',
        'sftp_password',
        'sftp_root',
        'sftp_host_fingerprint',
    ];

    /** @var list<string> */
    protected $hidden = ['sftp_password'];

    /** @var array<string, string> */
    protected $casts = [
        'mail_enabled' => 'boolean',
        'mail_recipients' => 'array',
        'sftp_enabled' => 'boolean',
        'sftp_port' => 'integer',
        'sftp_password' => 'encrypted',
    ];

    /** @return list<string> Bereinigte Empfängerliste des Mail-Kanals. */
    public function mailRecipients(): array {
        $recipients = [];
        foreach ($this->mail_recipients ?? [] as $mail) {
            if ($mail !== '') {
                $recipients[] = $mail;
            }
        }

        return $recipients;
    }

    /** Ist mindestens ein Lieferkanal vollständig konfiguriert und aktiv? */
    public function hasActiveChannel(): bool {
        return ($this->mail_enabled && $this->mailRecipients() !== [])
            || ($this->sftp_enabled && (string) $this->sftp_host !== '' && (string) $this->sftp_username !== '');
    }

    /**
     * Liefert die aktive Konfiguration einer Organisation für ein Profil —
     * bewusst ohne OrganizationScope, damit Queue-/CLI-Kontexte ohne
     * gebundene currentOrganization dieselbe Antwort erhalten.
     */
    public static function activeFor(int $organizationId, string $profile): ?self {
        $config = static::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('profile', $profile)
            ->first();

        return $config !== null && $config->hasActiveChannel() ? $config : null;
    }
}
