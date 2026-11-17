<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GwgIntakeCheckPackage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\Packages;

use App\Enums\Fields\FieldType;
use App\Enums\Protocol\ProtocolType;
use App\Enums\Sales\{IntakeRiskLevel, IntakeSensitivity};
use App\Models\Attachments\Attachment;
use App\Models\Platform\{Organization, User};
use App\Models\Protocol\ProtocolTemplate;
use App\Models\Sales\LeadIntake;
use App\Services\Fields\{FieldDefinition, FieldSchema, FieldValues};
use App\Services\Protocol\ProtocolService;
use App\Services\Sales\Intake\Contracts\{ActsOnIntakeAcceptance, ChecksIntakeBeforeAcceptance, IntakeCheckPackage};
use App\Services\Sales\Intake\Dto\IntakeRetentionRule;
use App\Services\Sales\Intake\{IntakeKeys, IntakePackages};

/**
 * Prüfpaket Geldwäschegesetz (Feature 200, MVP-1642) — Sorgfaltspflichten bei
 * Begründung einer Geschäftsbeziehung, Stand des GwG vom 23.06.2017 in der
 * geltenden Fassung (gesetze-im-internet.de/gwg_2017, geprüft 2026-11-12):
 *
 * - Identifizierung des Vertragspartners nach § 11 Abs. 4 Nr. 1 GwG (natürliche
 *   Person: Vor- und Nachname, Geburtsort, Geburtsdatum, Staatsangehörigkeit,
 *   Wohnanschrift) bzw. Nr. 2 (juristische Person: Firma, Rechtsform,
 *   Registernummer, Anschrift des Sitzes, Mitglieder des Vertretungsorgans);
 * - Überprüfung nach § 12 Abs. 1 bzw. 2 GwG; Art, Nummer und ausstellende Stelle
 *   des Dokuments und eine Kopie (§ 8 Abs. 2 GwG) — eng sichtbar und
 *   verschlüsselt ({@see IntakeSensitivity::Identity});
 * - wirtschaftlich Berechtigte (§ 3 GwG) mit Vermerk der Einsicht ins
 *   Transparenzregister (§ 11 Abs. 5, § 12 Abs. 3 GwG);
 * - politisch exponierte Person (§ 1 Abs. 12–14 GwG) — dann verstärkte
 *   Sorgfaltspflichten (§ 15 Abs. 3 Nr. 1 GwG), also Risikostufe hoch;
 * - Zweck und Art der Geschäftsbeziehung (§ 10 Abs. 1 Nr. 3 GwG);
 * - Risikofaktoren der Anlagen 1 und 2 als Ankreuzliste und Risikostufe
 *   gering/normal/hoch (§§ 14, 15 GwG) — gering und hoch mit Begründung
 *   (§ 8 Abs. 1 Satz 1 Nr. 2 GwG);
 * - Aufbewahrung fünf Jahre ab Schluss des Kalenderjahres, in dem die
 *   Geschäftsbeziehung endet (§ 8 Abs. 4 GwG).
 *
 * Keine Verdachtsmeldung an die FIU aus der App: Die Akte hält nur fest, dass
 * Anhaltspunkte geprüft wurden. Die Annahme legt den Vermerk
 * `STB_GELDWAESCHE_VERMERK` an.
 */
final class GwgIntakeCheckPackage implements ActsOnIntakeAcceptance, ChecksIntakeBeforeAcceptance, IntakeCheckPackage {
    public const string KEY = 'gwg';

    /** Katalogcode der Vorlage im Profil Steuerberatung (`database/data/protocol_templates.php`). */
    public const string PROTOCOL_TEMPLATE = 'STB_GELDWAESCHE_VERMERK';

    public const int RETENTION_YEARS = 5;

    /** Überprüfung mit Dokument — dann Art, Nummer, Stelle und Kopie (§ 8 Abs. 2 Satz 1 und 2 GwG). */
    private const array DOCUMENT_METHODS = ['id_document', 'register_extract'];

    private const array VERIFICATION_METHODS = ['id_document', 'eid', 'qes', 'register_extract', 'formation_documents', 'own_register_check'];

    /** Anlage 1 GwG — Faktoren für ein potenziell geringeres Risiko. */
    private const array LOWER_RISK_FACTORS = ['listed_company', 'public_body', 'low_risk_area', 'low_risk_product'];

    /** Anlage 2 GwG — Faktoren für ein potenziell höheres Risiko. */
    private const array HIGHER_RISK_FACTORS = ['unusual_circumstances', 'high_risk_area', 'asset_holding_vehicle', 'nominee_shareholders', 'cash_intensive', 'complex_structure', 'no_personal_contact', 'unknown_third_party_payments', 'high_risk_goods'];

    private const array PEP_STATUS = ['no', 'pep', 'family', 'associate'];

    public function __construct(
        private readonly IntakePackages $packages,
        private readonly ProtocolService $protocols,
    ) {}

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return (string) __('lead_intake.gwg.label');
    }

    public function schema(): FieldSchema {
        $natural = ['field' => 'party_kind', 'op' => 'eq', 'value' => 'natural'];
        $legal = ['field' => 'party_kind', 'op' => 'eq', 'value' => 'legal'];
        $withDocument = ['field' => 'verification_method', 'op' => 'in', 'value' => implode(',', self::DOCUMENT_METHODS)];

        return new FieldSchema([
            $this->section('identification'),
            $this->choice('party_kind', ['natural', 'legal'], required: true),
            $this->field('first_name', FieldType::Text, required: true, visibleIf: $natural),
            $this->field('last_name', FieldType::Text, required: true, visibleIf: $natural),
            $this->field('birth_place', FieldType::Text, required: true, visibleIf: $natural),
            $this->field('birth_date', FieldType::Date, required: true, visibleIf: $natural),
            $this->field('nationality', FieldType::Text, required: true, visibleIf: $natural),
            $this->field('home_address', FieldType::Textarea, required: true, visibleIf: $natural),
            $this->field('company_name', FieldType::Text, required: true, visibleIf: $legal),
            $this->field('legal_form', FieldType::Text, required: true, visibleIf: $legal),
            $this->field('register_number', FieldType::Text, visibleIf: $legal),
            $this->field('seat_address', FieldType::Textarea, required: true, visibleIf: $legal),
            $this->field('representatives', FieldType::Textarea, required: true, visibleIf: $legal),

            $this->section('verification'),
            $this->choice('verification_method', self::VERIFICATION_METHODS, required: true),
            $this->field('id_document_kind', FieldType::Text, required: true, visibleIf: $withDocument),
            $this->field('id_document_number', FieldType::Text, required: true, visibleIf: $withDocument),
            $this->field('id_issuer', FieldType::Text, required: true, visibleIf: $withDocument),
            $this->field('verified_on', FieldType::Date, required: true),

            $this->section('beneficial_owners'),
            $this->field('beneficial_owners', FieldType::Textarea),
            $this->field('transparency_register_checked', FieldType::Boolean, required: true, visibleIf: $legal),
            $this->field('transparency_register_on', FieldType::Date, visibleIf: $legal),
            $this->field('transparency_register_reference', FieldType::Text, visibleIf: $legal),

            $this->section('pep'),
            $this->choice('pep_status', self::PEP_STATUS, required: true),
            $this->field('pep_details', FieldType::Textarea, required: true, visibleIf: ['field' => 'pep_status', 'op' => 'ne', 'value' => 'no']),

            $this->section('purpose'),
            $this->field('purpose', FieldType::Textarea, required: true),
            $this->field('suspicion_checked', FieldType::Boolean, required: true),

            $this->section('risk'),
            $this->choice('lower_risk_factors', self::LOWER_RISK_FACTORS, multiple: true),
            $this->choice('higher_risk_factors', self::HIGHER_RISK_FACTORS, multiple: true),
            $this->riskLevel(),
            $this->field('risk_reason', FieldType::Textarea, required: true, visibleIf: ['field' => 'risk_level', 'op' => 'in', 'value' => IntakeRiskLevel::Low->value . ',' . IntakeRiskLevel::High->value]),
        ]);
    }

    public function documents(): array {
        return ['id_copy' => (string) __('lead_intake.gwg.document.id_copy')];
    }

    public function requiredBeforeDecision(): bool {
        return true;
    }

    public function retention(): IntakeRetentionRule {
        return new IntakeRetentionRule(self::RETENTION_YEARS, '§ 8 Abs. 4 GwG');
    }

    public function refreshInterval(FieldValues $values, Organization $organization): int {
        $level = IntakeRiskLevel::tryFrom((string) $values->get('risk_level')) ?? IntakeRiskLevel::Normal;

        return $this->packages->riskRefreshMonths($organization)[$level->value];
    }

    public function sensitiveFields(): array {
        $out = [];
        foreach (['birth_place', 'birth_date', 'nationality', 'home_address', 'id_document_kind', 'id_document_number', 'id_issuer', IntakeKeys::DOCUMENT_PREFIX . 'id_copy'] as $key) {
            $out[$key] = IntakeSensitivity::Identity;
        }

        return $out;
    }

    public function transfer(): array {
        return [];
    }

    /**
     * Über die Pflichtfelder hinaus: Bei einer politisch exponierten Person
     * gelten verstärkte Sorgfaltspflichten (§ 15 Abs. 3 Nr. 1 GwG), die
     * Risikostufe muss hoch sein; wer mit Dokument geprüft hat, legt die
     * Kopie ab (§ 8 Abs. 2 Satz 2 GwG) — an dieser oder einer früheren Fassung.
     */
    public function checkBeforeAcceptance(FieldValues $values, LeadIntake $intake): array {
        $errors = [];
        $pep = (string) $values->get('pep_status');
        if ($pep !== '' && $pep !== 'no' && $values->get('risk_level') !== IntakeRiskLevel::High->value) {
            $errors['risk_level'] = (string) __('lead_intake.gwg.error.pep_requires_high');
        }
        if (in_array((string) $values->get('verification_method'), self::DOCUMENT_METHODS, true)) {
            $meta = IntakeKeys::ATTACHMENT_META . IntakeKeys::document(self::KEY, 'id_copy');
            $hasCopy = Attachment::query()->withoutGlobalScopes()
                ->where('attachable_type', $intake->getMorphClass())
                ->whereIn('attachable_id', $intake->lineageIds())
                ->where('meta_type', $meta)
                ->exists();
            if (! $hasCopy) {
                $errors[IntakeKeys::DOCUMENT_PREFIX . 'id_copy'] = (string) __('lead_intake.gwg.error.copy_missing');
            }
        }

        return $errors;
    }

    /**
     * Vermerk aus der Vorlage `STB_GELDWAESCHE_VERMERK` am Lead (Profil
     * Steuerberatung); ohne Vorlage ein leerer Vermerk derselben Art. Er trägt
     * nur Angaben ohne enge Sichtbarkeit — Ausweisdaten bleiben in der Akte.
     */
    public function accepted(LeadIntake $intake, User $actor): void {
        $lead = $intake->lead;
        if ($lead === null || $intake->protocol_id !== null) {
            return;
        }
        $catalog = require database_path('data/protocol_templates.php');
        $name = is_array($catalog) ? (string) ($catalog[self::PROTOCOL_TEMPLATE]['name'] ?? '') : '';
        $template = $name === '' ? null : ProtocolTemplate::query()->usable()
            ->where('organization_id', $intake->organization_id)
            ->where('name', $name)
            ->orderByDesc('version')
            ->first();
        $values = IntakeKeys::scoped($intake->fieldDocumentValues(), self::KEY);
        $level = IntakeRiskLevel::tryFrom((string) $values->get('risk_level'));

        $protocol = $this->protocols->create($lead, $actor, array_filter([
            'type' => ProtocolType::Other->value,
            'title' => (string) __('lead_intake.gwg.protocol_title', ['name' => $lead->displayName()]),
            'description' => (string) __('lead_intake.gwg.protocol_description', [
                'risk' => $level?->label() ?? '—',
                'reason' => (string) ($values->get('risk_reason') ?? '—'),
                'revision' => $intake->revision,
            ]),
            'template_id' => $template?->id,
        ], static fn (mixed $value): bool => $value !== null));
        $intake->forceFill(['protocol_id' => $protocol->id])->saveQuietly();
    }

    /** Abschnitt; eigener Schlüsselraum `section_…`, damit er nie mit einem Feld gleichen Namens kollidiert. */
    private function section(string $key): FieldDefinition {
        return new FieldDefinition('section_' . $key, FieldType::Section, (string) __('lead_intake.gwg.section.' . $key));
    }

    /** @param  array{field: string, op: string, value: string}|null  $visibleIf */
    private function field(string $key, FieldType $type, bool $required = false, ?array $visibleIf = null): FieldDefinition {
        $help = __('lead_intake.gwg.help.' . $key);

        return new FieldDefinition(
            key: $key,
            type: $type,
            label: (string) __('lead_intake.gwg.field.' . $key),
            required: $required,
            help: $help === 'lead_intake.gwg.help.' . $key ? null : (string) $help,
            visibleIf: $visibleIf,
        );
    }

    /** @param  list<string>  $options */
    private function choice(string $key, array $options, bool $required = false, bool $multiple = false): FieldDefinition {
        $labels = [];
        foreach ($options as $option) {
            $labels[$option] = (string) __('lead_intake.gwg.option.' . $key . '.' . $option);
        }
        $help = __('lead_intake.gwg.help.' . $key);

        return new FieldDefinition(
            key: $key,
            type: $multiple ? FieldType::Multichoice : FieldType::Choice,
            label: (string) __('lead_intake.gwg.field.' . $key),
            required: $required,
            options: $options,
            help: $help === 'lead_intake.gwg.help.' . $key ? null : (string) $help,
            optionLabels: $labels,
        );
    }

    private function riskLevel(): FieldDefinition {
        $labels = [];
        foreach (IntakeRiskLevel::cases() as $level) {
            $labels[$level->value] = $level->label();
        }

        return new FieldDefinition(
            key: 'risk_level',
            type: FieldType::Choice,
            label: (string) __('lead_intake.gwg.field.risk_level'),
            required: true,
            options: array_keys($labels),
            help: (string) __('lead_intake.gwg.help.risk_level'),
            optionLabels: $labels,
        );
    }
}
