<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Club\ClubMembershipKind;
use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\Club\ClubMember;
use App\Models\Platform\Organization;
use App\Services\Club\ClubMemberService;
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\Concerns\ParsesLocalDateTime;
use CommonToolkit\Helper\Data\EmailHelper;
use Throwable;

/**
 * CSV-Spezifikation für den Erstimport von Vereinsmitgliedern (Feature 159,
 * MVP-842). Abgleichschlüssel ist die Mitgliedsnummer je Organisation; eine
 * gemeinsame Familien-E-Mail ist bewusst KEIN Dublettenschlüssel. Ohne
 * Nummer entsteht ein neues Mitglied mit der nächsten freien Nummer.
 * Bestehende Mitglieder werden nur in den Stammdaten aktualisiert — Art und
 * Eintritt bleiben dem Mitgliedschaftsverlauf vorbehalten.
 */
class ClubMemberSpec extends AbstractEntitySpec {
    use ParsesLocalDateTime;

    /** @var array<string, string> Deutsche Schreibweisen der Mitgliedschaftsart. */
    private const KIND_ALIASES = [
        'aktiv' => 'active',
        'passiv' => 'passive',
        'foerdermitglied' => 'supporting',
        'fördermitglied' => 'supporting',
        'foerdernd' => 'supporting',
        'fördernd' => 'supporting',
        'pausiert' => 'paused',
        'pause' => 'paused',
        'ruhend' => 'paused',
    ];

    public function __construct(
        private readonly ClubMemberService $members,
    ) {}

    public function entity(): ImportEntity {
        return ImportEntity::ClubMembers;
    }

    public function columns(): array {
        return ['member_no', 'first_name', 'last_name', 'email', 'phone', 'street', 'postal_code', 'city', 'birth_date', 'kind', 'joined_on', 'notes'];
    }

    public function requiredColumns(): array {
        return ['first_name', 'last_name'];
    }

    public function headerAliases(): array {
        return [
            'mitgliedsnummer' => 'member_no',
            'mitgliedsnr' => 'member_no',
            'mitgliedsnr.' => 'member_no',
            'mitglieds-nr' => 'member_no',
            'mitglied-nr' => 'member_no',
            'nummer' => 'member_no',
            'nr' => 'member_no',
            'vorname' => 'first_name',
            'nachname' => 'last_name',
            'name' => 'last_name',
            'familienname' => 'last_name',
            'e-mail' => 'email',
            'mail' => 'email',
            'telefon' => 'phone',
            'tel' => 'phone',
            'strasse' => 'street',
            'straße' => 'street',
            'plz' => 'postal_code',
            'postleitzahl' => 'postal_code',
            'ort' => 'city',
            'stadt' => 'city',
            'wohnort' => 'city',
            'geburtsdatum' => 'birth_date',
            'geburtstag' => 'birth_date',
            'geb' => 'birth_date',
            'art' => 'kind',
            'mitgliedschaft' => 'kind',
            'mitgliedschaftsart' => 'kind',
            'eintritt' => 'joined_on',
            'eintrittsdatum' => 'joined_on',
            'beitritt' => 'joined_on',
            'mitglied seit' => 'joined_on',
            'notizen' => 'notes',
            'bemerkung' => 'notes',
            'bemerkungen' => 'notes',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $this->trimmedString($row[$col] ?? null);
            $out[$col] = match ($col) {
                'member_no' => $raw !== null && ctype_digit($raw) ? (int) $raw : null,
                'email' => $this->lowerOrNull($raw),
                'birth_date', 'joined_on' => $this->normalizeImportDate($raw),
                'kind' => $this->kind($raw),
                default => $raw,
            };
            // Rohwert für die Formatprüfung behalten (normalisiert = null sagt nicht, ob leer oder ungültig).
            if (in_array($col, ['member_no', 'birth_date', 'joined_on', 'kind'], true)) {
                $out[$col . '_raw'] = $raw;
            }
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        unset($organization);
        $issues = [];

        foreach (['first_name', 'last_name'] as $required) {
            if (($row[$required] ?? null) === null) {
                $issues[] = $this->requiredIssue($required);
            }
        }
        if (($row['email'] ?? null) !== null && ! EmailHelper::isEmail((string) $row['email'])) {
            $issues[] = $this->formatIssue('email', (string) __('import.error.format.email'));
        }
        foreach (['member_no', 'birth_date', 'joined_on', 'kind'] as $checked) {
            if (($row[$checked . '_raw'] ?? null) !== null && ($row[$checked] ?? null) === null) {
                $issues[] = $this->formatIssue($checked, (string) __('import.error.format.' . ($checked === 'member_no' ? 'integer' : ($checked === 'kind' ? 'club_kind' : 'date'))));
            }
        }
        if (($row['member_no'] ?? null) !== null && (int) $row['member_no'] < 1) {
            $issues[] = new ValidationIssue(ImportErrorCode::OutOfRange, 'member_no', (string) __('club.error.member_no_invalid'));
        }
        if (($row['birth_date'] ?? null) !== null && $row['birth_date'] > now()->toDateString()) {
            $issues[] = new ValidationIssue(ImportErrorCode::OutOfRange, 'birth_date', (string) __('import.error.format.date'));
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            $existing = null;
            if (($row['member_no'] ?? null) !== null) {
                $existing = ClubMember::withTrashed()
                    ->where('organization_id', $organization->id)
                    ->where('member_no', (int) $row['member_no'])
                    ->first();
            }

            if ($existing !== null) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $update = array_filter([
                    'first_name' => $row['first_name'] ?? null,
                    'last_name' => $row['last_name'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'street' => $row['street'] ?? null,
                    'postal_code' => $row['postal_code'] ?? null,
                    'city' => $row['city'] ?? null,
                    'birth_date' => $row['birth_date'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ], static fn($value): bool => $value !== null);
                $this->members->update($existing, $update);

                return [ImportOutcome::Updated, null];
            }

            $this->members->create($organization, null, [
                'member_no' => $row['member_no'] ?? null,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'street' => $row['street'] ?? null,
                'postal_code' => $row['postal_code'] ?? null,
                'city' => $row['city'] ?? null,
                'birth_date' => $row['birth_date'] ?? null,
                'kind' => $row['kind'] ?? ClubMembershipKind::Active->value,
                'joined_on' => $row['joined_on'] ?? null,
                'notes' => $row['notes'] ?? null,
            ]);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, 'member_no', $e->getMessage())];
        }
    }

    private function kind(?string $raw): ?string {
        if ($raw === null) {
            return null;
        }
        $key = mb_strtolower($raw);
        if (ClubMembershipKind::tryFrom($key) instanceof ClubMembershipKind) {
            return $key;
        }

        return self::KIND_ALIASES[$key] ?? null;
    }
}
