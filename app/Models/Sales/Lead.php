<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Lead.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Sales\{LeadSource, LeadStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany};
use Illuminate\Support\Carbon;

/**
 * Lead-Akte (Feature 091): ein Interessent VOR dem Kundenstatus.
 *
 * Kein CRM-Vollprodukt: Qualifizierung läuft über die vorhandenen
 * Kommunikationsnotizen (inkl. Wiedervorlage), die Konvertierung erzeugt nach
 * Dublettenprüfung genau einen Kunden, und nicht konvertierte Leads werden
 * nach Org-Frist anonymisiert — personenbezogene Daten ohne Vertrag haben
 * keinen Anspruch auf Dauer-Aufbewahrung.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string|null $company
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property LeadSource $source
 * @property string|null $interest
 * @property LeadStatus $status
 * @property string|null $discard_reason
 * @property int|null $responsible_user_id
 * @property int|null $customer_id
 * @property Carbon|null $last_contact_at
 * @property Carbon|null $anonymized_at
 * @property int|null $created_by
 */
class Lead extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'company', 'contact_name', 'email', 'phone',
        'source', 'interest', 'status', 'discard_reason',
        'responsible_user_id', 'customer_id', 'last_contact_at',
        'anonymized_at', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'source' => LeadSource::class,
        'status' => LeadStatus::class,
        'last_contact_at' => 'datetime',
        'anonymized_at' => 'datetime',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return MorphMany<CommunicationNote, $this> */
    public function communicationNotes(): MorphMany {
        return $this->morphMany(CommunicationNote::class, 'notable');
    }

    /** Anzeigename: Firma, sonst Ansprechpartner, sonst Platzhalter. */
    public function displayName(): string {
        return trim((string) ($this->company ?: $this->contact_name)) ?: (string) __('Anonymisierter Lead');
    }
}
