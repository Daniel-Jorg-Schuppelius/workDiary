<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RenderProfileService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\DocumentDesign;

use App\Enums\DocumentDesign\{InformationBlock, InformationBlockState, LetterheadPageRole, RenderDocumentKind, RenderProfileStatus, TableStylePreset};
use App\Models\DocumentDesign\{DocumentRenderProfile, DocumentRenderProfileVersion, LetterheadAsset};
use App\Models\{Organization, User};
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Verwaltung der Renderprofile (MVP-300): Anlage, Entwurfsbearbeitung,
 * Aktivierung (nur mit grünem Preflight), Zuweisung je Dokumentart, Fallback
 * auf das org-weite Standardprofil und kontrolliertes Zurücksetzen über eine
 * neue Version — alte Stände bleiben unverändert erhalten.
 */
class RenderProfileService {
    public function __construct(private readonly RenderPreflightService $preflight) {}

    /** @param array<int, string> $kinds */
    public function createProfile(Organization $organization, string $name, array $kinds, bool $isDefault, ?User $user = null): DocumentRenderProfile {
        return DB::transaction(function () use ($organization, $name, $kinds, $isDefault, $user): DocumentRenderProfile {
            $profile = DocumentRenderProfile::create([
                'organization_id' => $organization->id,
                'name' => $name,
                'status' => RenderProfileStatus::Draft,
                'is_default' => false,
                'document_kinds' => $this->normalizeKinds($kinds),
            ]);

            $profile->versions()->create([
                'organization_id' => $organization->id,
                'version' => 1,
                'status' => DocumentRenderProfileVersion::STATUS_DRAFT,
                'layout' => self::defaultLayout(),
                'block_rules' => self::defaultBlockRules(),
                'table_style' => ['preset' => TableStylePreset::Clear->value, 'overrides' => []],
                'created_by' => $user?->id,
            ]);

            if ($isDefault) {
                $this->setDefault($profile);
            }

            return $profile;
        });
    }

    /**
     * Entwurf bearbeiten. Aktivierte Versionen sind unveränderlich; für
     * Änderungen wird zuerst ein neuer Entwurf erzeugt (newDraftFrom).
     *
     * @param array<string, mixed> $data
     */
    public function updateDraft(DocumentRenderProfileVersion $version, array $data, ?User $user = null): DocumentRenderProfileVersion {
        if (! $version->isDraft()) {
            throw new RuntimeException(__('Nur Entwürfe können bearbeitet werden.'));
        }

        if (array_key_exists('layout', $data)) {
            $version->layout = $this->sanitizeLayout((array) $data['layout']);
        }
        if (array_key_exists('block_rules', $data)) {
            $version->block_rules = $this->sanitizeBlockRules((array) $data['block_rules'], $user);
        }
        if (array_key_exists('table_style', $data)) {
            $version->table_style = $this->sanitizeTableStyle((array) $data['table_style']);
        }
        foreach ([['first_asset_id', LetterheadPageRole::First], ['following_asset_id', LetterheadPageRole::Following]] as [$key, $role]) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $assetId = $data[$key];
            if ($assetId !== null) {
                $asset = LetterheadAsset::query()
                    ->where('organization_id', $version->organization_id)
                    ->where('page_role', $role)
                    ->whereKey($assetId)
                    ->firstOrFail();
                if (! $asset->isReady()) {
                    throw new InvalidArgumentException(__('Der Firmenbogen ist nicht einsatzbereit.'));
                }
                $assetId = $asset->id;
            }
            $version->{$key} = $assetId;
        }

        $version->save();

        return $version;
    }

    /** Neue Entwurfsversion als Kopie eines bestehenden Standes (auch Rollback). */
    public function newDraftFrom(DocumentRenderProfileVersion $source, ?User $user = null): DocumentRenderProfileVersion {
        $profile = $source->profile;
        if ($profile === null) {
            throw new RuntimeException('Profilversion ohne Profil.');
        }
        if ($profile->versions()->where('status', DocumentRenderProfileVersion::STATUS_DRAFT)->exists()) {
            throw new RuntimeException(__('Es existiert bereits ein offener Entwurf.'));
        }

        $next = (int) $profile->versions()->max('version') + 1;

        return $profile->versions()->create([
            'organization_id' => $profile->organization_id,
            'version' => $next,
            'status' => DocumentRenderProfileVersion::STATUS_DRAFT,
            'first_asset_id' => $source->first_asset_id,
            'following_asset_id' => $source->following_asset_id,
            'layout' => $source->layout,
            'block_rules' => $source->block_rules,
            'table_style' => $source->table_style,
            'created_by' => $user?->id,
        ]);
    }

    /** Aktivierung nur mit fehlerfreiem Preflight (MVP-298: kein stilles Loch). */
    public function activate(DocumentRenderProfileVersion $version, ?User $user = null): PreflightResult {
        $profile = $version->profile;
        if ($profile === null) {
            throw new RuntimeException('Profilversion ohne Profil.');
        }
        if (! $version->isDraft()) {
            throw new RuntimeException(__('Nur Entwürfe können aktiviert werden.'));
        }

        $result = $this->preflight->check($version, $profile->document_kinds ?? []);
        if (! $result->ok()) {
            return $result;
        }

        DB::transaction(function () use ($version, $profile, $user): void {
            $profile->versions()
                ->where('status', DocumentRenderProfileVersion::STATUS_ACTIVE)
                ->update(['status' => DocumentRenderProfileVersion::STATUS_SUPERSEDED]);

            $version->forceFill([
                'status' => DocumentRenderProfileVersion::STATUS_ACTIVE,
                'activated_at' => now(),
                'activated_by' => $user?->id,
                'checksum' => CryptoHelper::hash(JsonHelper::encode([
                    $version->layout, $version->block_rules, $version->table_style,
                    $version->first_asset_id, $version->following_asset_id,
                ])),
            ])->save();

            $profile->forceFill([
                'status' => RenderProfileStatus::Active,
                'active_version_id' => $version->id,
            ])->save();

            $profile->audit('render_profile_activated', ['version' => $version->version]);
        });

        return $result;
    }

    /** @param array<int, string> $kinds */
    public function assignKinds(DocumentRenderProfile $profile, array $kinds): void {
        $profile->document_kinds = $this->normalizeKinds($kinds);
        $profile->save();
    }

    /** Org-weites Standardprofil: genau eines je Organisation. */
    public function setDefault(DocumentRenderProfile $profile): void {
        DB::transaction(function () use ($profile): void {
            DocumentRenderProfile::query()
                ->where('organization_id', $profile->organization_id)
                ->where('id', '!=', $profile->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
            $profile->forceFill(['is_default' => true])->save();
        });
    }

    public function archive(DocumentRenderProfile $profile): void {
        $profile->forceFill(['status' => RenderProfileStatus::Archived, 'is_default' => false])->save();
    }

    /**
     * Profilauflösung (MVP-300): dokumentartspezifisches aktives Profil mit
     * höchster Priorität, sonst org-weites Standardprofil, sonst null
     * (= Systemfallback, heutige Ausgabe).
     */
    public function resolveFor(Organization $organization, RenderDocumentKind $kind): ?DocumentRenderProfileVersion {
        $profiles = DocumentRenderProfile::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RenderProfileStatus::Active)
            ->whereNotNull('active_version_id')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $match = $profiles->first(fn(DocumentRenderProfile $p) => $p->coversKind($kind))
            ?? $profiles->first(fn(DocumentRenderProfile $p) => $p->is_default);

        return $match?->activeVersion()->withoutGlobalScopes()->first();
    }

    /** @return array<string, mixed> Standardlayout = heutige Ausgabe (20 mm Ränder, kein Firmenbogen). */
    public static function defaultLayout(): array {
        return [
            'page' => ['format' => 'a4_portrait', 'min_edge_mm' => 5],
            'content_first' => ['top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20],
            'content_following' => ['top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20],
            'address_window' => null,
            'sender_line' => null,
            'footer' => ['page_numbers' => false, 'carryover_note' => false],
            'blocked_areas' => [],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function defaultBlockRules(): array {
        $rules = [];
        foreach (InformationBlock::cases() as $block) {
            $rules[$block->value] = ['state' => $block->defaultState()->value];
        }

        return $rules;
    }

    /**
     * @param  array<int, string>  $kinds
     * @return array<int, string>
     */
    private function normalizeKinds(array $kinds): array {
        return array_values(array_unique(array_filter(
            $kinds,
            fn($kind) => RenderDocumentKind::tryFrom((string) $kind) !== null,
        )));
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    private function sanitizeLayout(array $layout): array {
        $clean = self::defaultLayout();

        foreach (['content_first', 'content_following'] as $key) {
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $clean[$key][$side] = $this->mm($layout[$key][$side] ?? $clean[$key][$side]);
            }
        }
        foreach ([['address_window', ['x', 'y', 'width', 'height']], ['sender_line', ['x', 'y', 'width']]] as [$key, $fields]) {
            $box = $layout[$key] ?? null;
            $clean[$key] = null;
            if (is_array($box)) {
                $clean[$key] = [];
                foreach ($fields as $field) {
                    $clean[$key][$field] = $this->mm($box[$field] ?? 0);
                }
            }
        }
        $clean['footer'] = [
            'page_numbers' => (bool) ($layout['footer']['page_numbers'] ?? false),
            'carryover_note' => (bool) ($layout['footer']['carryover_note'] ?? false),
        ];
        $clean['blocked_areas'] = [];
        foreach ((array) ($layout['blocked_areas'] ?? []) as $area) {
            if (! is_array($area)) {
                continue;
            }
            $clean['blocked_areas'][] = [
                'page' => in_array($area['page'] ?? 'all', ['first', 'following', 'all'], true) ? $area['page'] : 'all',
                'x' => $this->mm($area['x'] ?? 0),
                'y' => $this->mm($area['y'] ?? 0),
                'width' => $this->mm($area['width'] ?? 0),
                'height' => $this->mm($area['height'] ?? 0),
                'label' => mb_substr(trim((string) ($area['label'] ?? '')), 0, 80),
            ];
        }

        return $clean;
    }

    /**
     * Blockregeln absichern: dynamicOnly-Blöcke können nie vom Firmenbogen
     * bereitgestellt werden; die Abdeckungsbestätigung wird je Profilversion
     * mit Nutzer und Zeitpunkt festgehalten (MVP-298).
     *
     * @param array<string, mixed> $rules
     * @return array<string, array<string, mixed>>
     */
    private function sanitizeBlockRules(array $rules, ?User $user): array {
        $clean = [];
        foreach (InformationBlock::cases() as $block) {
            $raw = (array) ($rules[$block->value] ?? []);
            $state = InformationBlockState::tryFrom((string) ($raw['state'] ?? '')) ?? $block->defaultState();
            if ($block->dynamicOnly() && $state === InformationBlockState::ProvidedByLetterhead) {
                throw new InvalidArgumentException(__('„:block" enthält veränderliche Belegdaten und kann nicht vom Firmenbogen bereitgestellt werden.', ['block' => $block->label()]));
            }

            $entry = ['state' => $state->value];
            if ($state === InformationBlockState::ProvidedByLetterhead && ! empty($raw['confirmed'])) {
                $entry['confirmed'] = true;
                $entry['confirmed_by'] = $user?->id;
                $entry['confirmed_at'] = now()->toIso8601String();
            }
            $clean[$block->value] = $entry;
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private function sanitizeTableStyle(array $style): array {
        $preset = TableStylePreset::tryFrom((string) ($style['preset'] ?? '')) ?? TableStylePreset::Clear;
        $bounds = TableStylePreset::bounds();
        $overrides = [];

        foreach ((array) ($style['overrides'] ?? []) as $key => $value) {
            $bound = $bounds[$key] ?? null;
            if ($bound === null) {
                continue;
            }
            $overrides[$key] = match ($bound['type']) {
                'number' => max((float) ($bound['min'] ?? 0), min((float) ($bound['max'] ?? PHP_FLOAT_MAX), (float) $value)),
                'bool' => (bool) $value,
                'option' => in_array((string) $value, $bound['options'] ?? [], true) ? (string) $value : null,
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) === 1 ? strtolower((string) $value) : null,
                default => null,
            };
            if ($overrides[$key] === null) {
                unset($overrides[$key]);
            }
        }

        return ['preset' => $preset->value, 'overrides' => $overrides];
    }

    private function mm(mixed $value): float {
        return round(max(0.0, min(297.0, (float) $value)), 1);
    }
}
