<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sales;

use App\Casts\FieldDocumentCast;
use App\Enums\Sales\{IntakeSensitivity, LeadIntakeDecision, LeadIntakeStage};
use App\Models\Attachments\Attachment;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\Contracts\{AutosavesFieldDocument, GuardsAttachments};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Protocol\Protocol;
use App\Services\Fields\{FieldDocument, FieldSchema, FieldValues};
use App\Services\Sales\Intake\Dto\IntakePackageSnapshot;
use App\Services\Sales\Intake\LeadIntakeVisibility;
use Database\Factories\Sales\LeadIntakeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Aufnahmeakte am Lead (Feature 200, MVP-1641): eine Fassung je Zeile —
 * die Aktualisierung legt eine neue an (`supersedes_id`, `revision`), die
 * alte bleibt als Verlauf.
 *
 * Der Bogen ist ein Feldschema-Dokument (`form`: eingefrorenes Schema aus
 * Grundteil, Fristmerkmalen und Prüfpaketen samt Werten); Angaben mit enger
 * Sichtbarkeit (Ausweis, Gesundheit) stehen verschlüsselt in
 * `sensitive_values` und kommen nur über {@see fieldDocumentValues()} dazu.
 * Speichern je Feld über den Vertrag {@see AutosavesFieldDocument} (Feature
 * 203): wer eine enge Angabe nicht sehen darf, bekommt sie im Schema nicht
 * angeboten und kann sie nicht schreiben.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $lead_id
 * @property int|null $customer_id
 * @property int $revision
 * @property int|null $supersedes_id
 * @property Carbon|null $superseded_at
 * @property LeadIntakeStage $stage
 * @property list<array<string, mixed>>|null $packages
 * @property FieldDocument|null $form
 * @property array<string, mixed>|null $sensitive_values
 * @property int $version
 * @property LeadIntakeDecision|null $decision
 * @property string|null $decision_reason
 * @property int|null $decider_user_id
 * @property Carbon|null $decided_at
 * @property int|null $second_reviewer_user_id
 * @property Carbon|null $second_reviewed_at
 * @property int|null $protocol_id
 * @property Carbon|null $refresh_due_on
 * @property Carbon|null $refresh_warned_at
 * @property Carbon|null $relationship_ended_on
 * @property Carbon|null $retention_until
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LeadIntake extends Model implements AutosavesFieldDocument, GuardsAttachments {
    use Auditable {
        auditRedact as private baseAuditRedact;
    }
    use BelongsToOrganization;
    use HasAttachments;
    /** @use HasFactory<LeadIntakeFactory> */
    use HasFactory;
    use HasSqid;

    protected $table = 'lead_intakes';

    protected $fillable = [
        'organization_id',
        'lead_id',
        'customer_id',
        'revision',
        'supersedes_id',
        'superseded_at',
        'stage',
        'packages',
        'form',
        'sensitive_values',
        'version',
        'decision',
        'decision_reason',
        'decider_user_id',
        'decided_at',
        'second_reviewer_user_id',
        'second_reviewed_at',
        'protocol_id',
        'refresh_due_on',
        'refresh_warned_at',
        'relationship_ended_on',
        'retention_until',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'stage' => LeadIntakeStage::class,
        'decision' => LeadIntakeDecision::class,
        'packages' => 'array',
        'form' => FieldDocumentCast::class,
        'sensitive_values' => 'encrypted:array',
        'revision' => 'integer',
        'version' => 'integer',
        'superseded_at' => 'datetime',
        'decided_at' => 'datetime',
        'second_reviewed_at' => 'datetime',
        'refresh_due_on' => 'date',
        'refresh_warned_at' => 'datetime',
        'relationship_ended_on' => 'date',
        'retention_until' => 'date',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'stage' => 'inquiry',
        'revision' => 1,
        'version' => 1,
    ];

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo {
        return $this->belongsTo(User::class, 'decider_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function secondReviewer(): BelongsTo {
        return $this->belongsTo(User::class, 'second_reviewer_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Protocol, $this> */
    public function protocol(): BelongsTo {
        return $this->belongsTo(Protocol::class);
    }

    /** @return BelongsTo<self, $this> */
    public function supersedes(): BelongsTo {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** Neueste Fassung (nicht durch eine Aktualisierung abgelöst)? */
    public function isLatest(): bool {
        return $this->superseded_at === null;
    }

    /**
     * Eingefrorene Prüfpakete der Akte.
     *
     * @return list<IntakePackageSnapshot>
     */
    public function packageSnapshots(): array {
        $out = [];
        foreach ($this->packages ?? [] as $row) {
            $snapshot = IntakePackageSnapshot::fromArray($row);
            if ($snapshot !== null) {
                $out[] = $snapshot;
            }
        }

        return $out;
    }

    /** Sperrt ein Paket der Akte die Umwandlung ohne Annahme? */
    public function requiresDecision(): bool {
        foreach ($this->packageSnapshots() as $package) {
            if ($package->requiresDecision) {
                return true;
            }
        }

        return false;
    }

    /** Hält ein Paket die Akte über die Löschung des Leads hinaus (Aufbewahrungsregel)? */
    public function retains(): bool {
        foreach ($this->packageSnapshots() as $package) {
            if ($package->retention !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Felder und Unterlagen mit enger Sichtbarkeit (Schlüssel mit Paketpräfix).
     *
     * @return array<string, IntakeSensitivity>
     */
    public function sensitiveMap(): array {
        $out = [];
        foreach ($this->packageSnapshots() as $package) {
            $out += $package->sensitive;
        }

        return $out;
    }

    /**
     * Ist die Angabe der Person verborgen (enge Sichtbarkeit ohne eigenes
     * Recht)? Dann die Bezeichnung des nötigen Rechts, sonst null — für
     * Auskunft und Ausgaben außerhalb des Moduls.
     */
    public function restrictionFor(string $key, ?User $viewer): ?string {
        $sensitivity = $this->sensitiveMap()[$key] ?? null;
        if ($sensitivity === null || app(LeadIntakeVisibility::class)->canSee($viewer, $sensitivity)) {
            return null;
        }

        return $sensitivity->permission()->label();
    }

    /** Angenommen und — falls verlangt — durch den zweiten Blick bestätigt. */
    public function isAccepted(): bool {
        return $this->decision === LeadIntakeDecision::Accepted
            && ($this->stage === LeadIntakeStage::Setup || $this->stage === LeadIntakeStage::Completed);
    }

    /** Angenommen, aber der zweite Blick steht noch aus. */
    public function awaitsSecondReview(): bool {
        return $this->decision === LeadIntakeDecision::Accepted && $this->stage === LeadIntakeStage::Decision;
    }

    /** Vollständiges Schema des Bogens (ohne Rücksicht auf Rechte). */
    public function fullSchema(): FieldSchema {
        return $this->form->schema ?? new FieldSchema([]);
    }

    /**
     * IDs dieser und aller früheren Fassungen (Anhänge der Kette, etwa die
     * Ausweiskopie der Erstidentifizierung).
     *
     * @return list<int>
     */
    public function lineageIds(): array {
        $ids = [(int) $this->id];
        $previous = $this->supersedes_id;
        $guard = 0;
        while ($previous !== null && $guard++ < 50) {
            $ids[] = (int) $previous;
            $previous = self::query()->withoutGlobalScopes()->whereKey($previous)->value('supersedes_id');
            $previous = is_numeric($previous) ? (int) $previous : null;
        }

        return $ids;
    }

    /**
     * Werte so ablegen, wie sie hingehören: enge Angaben (samt Ergänzungen)
     * verschlüsselt, alles andere im Bogen.
     */
    public function fillValues(FieldValues $values): void {
        $sensitive = $this->sensitiveMap();
        $plain = [];
        $hidden = [];
        foreach ($values->toArray() as $key => $value) {
            if ($key === FieldValues::NOTES_KEY) {
                continue;
            }
            if (isset($sensitive[$key])) {
                $hidden[$key] = $value;
            } else {
                $plain[$key] = $value;
            }
        }
        foreach ($values->notes() as $key => $note) {
            if (isset($sensitive[$key])) {
                $hidden[FieldValues::NOTES_KEY][$key] = $note;
            } else {
                $plain[FieldValues::NOTES_KEY][$key] = $note;
            }
        }
        $this->form = new FieldDocument($this->fullSchema(), new FieldValues($plain));
        $this->sensitive_values = $hidden === [] ? null : $hidden;
    }

    // ── AutosavesFieldDocument (Feature 203) ──────────────────────────

    /** Das Schema, das die angemeldete Person sehen und schreiben darf. */
    public function fieldDocumentSchema(): FieldSchema {
        return app(LeadIntakeVisibility::class)->schemaFor($this, Auth::user());
    }

    /** Alle Werte, enge Angaben eingeschlossen (geprüft wird über das Schema). */
    public function fieldDocumentValues(): FieldValues {
        $plain = $this->form?->values->toArray() ?? [];
        $hidden = $this->sensitive_values ?? [];
        $notes = array_merge((array) ($plain[FieldValues::NOTES_KEY] ?? []), (array) ($hidden[FieldValues::NOTES_KEY] ?? []));
        unset($plain[FieldValues::NOTES_KEY], $hidden[FieldValues::NOTES_KEY]);
        $values = array_merge($plain, $hidden);
        if ($notes !== []) {
            $values[FieldValues::NOTES_KEY] = $notes;
        }

        return new FieldValues($values);
    }

    public function fieldDocumentVersion(): int {
        return (int) $this->version;
    }

    /** Bis zur Entscheidung, nur die neueste Fassung. */
    public function acceptsFieldAutosave(): bool {
        return $this->isLatest() && $this->decision === null && $this->stage->isEditable();
    }

    /** Zwischenstand still schreiben — protokolliert werden Stufe und Entscheidung, nicht jedes Feld. */
    public function storeFieldAutosave(FieldValues $values, int $version): void {
        $this->fillValues($values);
        $this->forceFill(['version' => $version, 'updated_by' => Auth::id()])->saveQuietly();
    }

    // ── GuardsAttachments ─────────────────────────────────────────────

    /** Nach der Entscheidung sind die Unterlagen Nachweis: nichts kommt hinzu, nichts entfällt. */
    public function attachmentsLocked(?Attachment $attachment = null): bool {
        return ! $this->acceptsFieldAutosave();
    }

    public function attachmentAdded(Attachment $attachment, ?User $actor): void {
        $this->audit('lead_intake.document_added', $this->attachmentAuditPayload($attachment));
    }

    public function attachmentRemoved(Attachment $attachment, ?User $actor): void {
        $this->audit('lead_intake.document_removed', $this->attachmentAuditPayload($attachment));
    }

    /**
     * Unterlage im Protokoll — der Dateiname einer eng sichtbaren Unterlage
     * (etwa „Ausweis_Vorname_Nachname.jpg“) bleibt draußen.
     *
     * @return array<string, string|null>
     */
    private function attachmentAuditPayload(Attachment $attachment): array {
        $meta = (string) $attachment->meta_type;
        $key = str_starts_with($meta, \App\Services\Sales\Intake\IntakeKeys::ATTACHMENT_META) ? substr($meta, strlen(\App\Services\Sales\Intake\IntakeKeys::ATTACHMENT_META)) : $meta;

        return ['meta' => $meta, 'file' => isset($this->sensitiveMap()[$key]) ? null : $attachment->original_name];
    }

    /**
     * Der Bogen trägt Personenangaben — das Protokoll vermerkt Änderungen,
     * aber nicht die Werte; verschlüsselte Spalten ohnehin nicht.
     *
     * @return array<int, string>
     */
    protected function auditRedact(): array {
        return [...$this->baseAuditRedact(), 'form'];
    }
}
