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

use App\Enums\DocumentDesign\{InformationBlock, InformationBlockState, LetterheadPageRole, RenderDocumentFamily, RenderDocumentKind, RenderProfileStatus, TableStylePreset};
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
    public function createProfile(Organization $organization, string $name, array $kinds, bool $isDefault, ?User $user = null, ?RenderDocumentFamily $family = null): DocumentRenderProfile {
        return DB::transaction(function () use ($organization, $name, $kinds, $isDefault, $user, $family): DocumentRenderProfile {
            $profile = DocumentRenderProfile::create([
                'organization_id' => $organization->id,
                'name' => $name,
                'status' => RenderProfileStatus::Draft,
                'is_default' => false,
                'document_kinds' => $this->normalizeKinds($kinds),
                'document_family' => $family?->value,
            ]);

            // Varianten (#83) erben standardmäßig vollständig vom Basisdesign
            // ([] = keine Overrides); das Basisdesign selbst und Organisationen
            // ohne Basisdesign starten eigenständig (null = Bestandsverhalten).
            $inherits = ! $isDefault && $this->baseProfile($organization) !== null;

            $profile->versions()->create([
                'organization_id' => $organization->id,
                'version' => 1,
                'status' => DocumentRenderProfileVersion::STATUS_DRAFT,
                'layout' => self::defaultLayout(),
                'block_rules' => self::defaultBlockRules(),
                'table_style' => ['preset' => TableStylePreset::Clear->value, 'overrides' => []],
                'override_sections' => $inherits ? [] : null,
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
        if (array_key_exists('content_texts', $data)) {
            $version->content_texts = $this->sanitizeContentTexts($data['content_texts']);
        }
        if (array_key_exists('override_sections', $data)) {
            $version->override_sections = $this->sanitizeOverrideSections($data['override_sections']);
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
            'content_texts' => $source->content_texts,
            'override_sections' => $source->override_sections,
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

        // Erbende Varianten werden gegen ihren EFFEKTIVEN Stand geprüft
        // (Basisdesign + Overrides) — die spärlich gespeicherte Version allein
        // wäre kein sinnvoller Prüfgegenstand (#83).
        $result = $this->preflight->check($this->effectiveVersion($version), $this->preflightKinds($profile));
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

    /**
     * Prüfumfang der Aktivierung (#83, schließt die Lücke aus MVP-298):
     * dokumentartgebundene Profile prüfen ihre Arten; ein Standard-/
     * CI-Basisprofil ohne (oder ohne vollständige) Art-Bindung dient als
     * Fallback für ALLE brandfähigen Arten und wird deshalb gegen deren
     * gesamte Pflichtblock-Vereinigung geprüft — vorher wurde ein
     * Default-Profil ohne `document_kinds` gegen nichts geprüft.
     *
     * @return array<int, string>
     */
    private function preflightKinds(DocumentRenderProfile $profile): array {
        $kinds = $profile->document_kinds ?? [];
        if ($profile->document_family !== null) {
            foreach (RenderDocumentKind::brandable() as $kind) {
                if ($kind->family() === $profile->document_family) {
                    $kinds[] = $kind->value;
                }
            }
        }
        if ($profile->is_default) {
            foreach (RenderDocumentKind::brandable() as $kind) {
                $kinds[] = $kind->value;
            }
        }

        return array_values(array_unique($kinds));
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
     * Profilauflösung (MVP-300; Ausbau #83) — die spezifischere Variante
     * gewinnt: dokumentartspezifisches aktives Profil mit höchster Priorität,
     * sonst Familien-Variante, sonst das Profil der etablierten Fallback-Art
     * ({@see RenderDocumentKind::fallbackKind()} — z. B. Gutschrift →
     * Rechnungsprofil, Bestandskompatibilität), sonst org-weites
     * CI-Basisdesign (is_default), sonst null (= Systemfallback,
     * heutige Ausgabe).
     */
    public function resolveFor(Organization $organization, RenderDocumentKind $kind, ?int $customerId = null): ?DocumentRenderProfileVersion {
        $profiles = $this->activeProfiles($organization);

        // Kunden-Sonderdesign (MVP-651): das an der Kundenakte referenzierte
        // aktive Profil gewinnt vor der org-weiten Kette — sofern es die Art
        // abdeckt (explizite Arten, Familie oder ohne jede Einschränkung).
        if ($customerId !== null) {
            $profileId = \App\Models\Customer::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereKey($customerId)
                ->value('document_render_profile_id');
            if ($profileId !== null) {
                $customerProfile = $profiles->first(fn(DocumentRenderProfile $p) => (int) $p->id === (int) $profileId);
                if ($customerProfile !== null && $this->coversForCustomer($customerProfile, $kind)) {
                    return $customerProfile->activeVersion()->withoutGlobalScopes()->first();
                }
            }
        }

        // Org-weite Kette: Kunden-Sonderprofile nehmen hier nicht teil.
        $orgWide = $profiles->reject(fn(DocumentRenderProfile $p) => $p->is_customer_specific)->values();

        $fallbackKind = $kind->fallbackKind();
        $match = $orgWide->first(fn(DocumentRenderProfile $p) => $p->coversKind($kind))
            ?? $orgWide->first(fn(DocumentRenderProfile $p) => $p->coversFamily($kind))
            ?? ($fallbackKind !== null ? $orgWide->first(fn(DocumentRenderProfile $p) => $p->coversKind($fallbackKind)) : null)
            ?? $orgWide->first(fn(DocumentRenderProfile $p) => $p->is_default);

        return $match?->activeVersion()->withoutGlobalScopes()->first();
    }

    /** Deckt ein Kunden-Sonderprofil die Art ab? Ohne Bindung gilt es für alle Arten. */
    private function coversForCustomer(DocumentRenderProfile $profile, RenderDocumentKind $kind): bool {
        $hasKinds = ($profile->document_kinds ?? []) !== [];

        return $profile->coversKind($kind)
            || $profile->coversFamily($kind)
            || (! $hasKinds && $profile->document_family === null);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, DocumentRenderProfile> */
    private function activeProfiles(Organization $organization): \Illuminate\Database\Eloquent\Collection {
        return DocumentRenderProfile::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RenderProfileStatus::Active)
            ->whereNotNull('active_version_id')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    /** Das CI-Basisdesign der Organisation (is_default-Profil), unabhängig vom Status. */
    public function baseProfile(Organization $organization): ?DocumentRenderProfile {
        return DocumentRenderProfile::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('is_default', true)
            ->where('status', '!=', RenderProfileStatus::Archived)
            ->orderBy('id')
            ->first();
    }

    /** Aktive Version des CI-Basisdesigns (oder null, wenn keines aktiv ist). */
    public function baseVersionFor(Organization $organization): ?DocumentRenderProfileVersion {
        $base = $this->activeProfiles($organization)->first(fn(DocumentRenderProfile $p) => $p->is_default);

        return $base?->activeVersion()->withoutGlobalScopes()->first();
    }

    /**
     * Effektiver Stand einer Version (#83): erbt die Version vom Basisdesign
     * (`override_sections` gesetzt, Profil ist nicht selbst das Basisdesign),
     * werden alle nicht überschriebenen Sektionen (layout, assets,
     * block_rules, table_style) aus der aktiven Basisdesign-Version gelesen.
     * Rückgabe ist dann eine NICHT persistierte Instanz mit den gemergten
     * Werten; eigenständige Versionen kommen unverändert zurück.
     */
    public function effectiveVersion(DocumentRenderProfileVersion $version): DocumentRenderProfileVersion {
        return $this->withResolvedBrandColors($this->mergedWithBase($version));
    }

    private function mergedWithBase(DocumentRenderProfileVersion $version): DocumentRenderProfileVersion {
        $overrides = $version->override_sections;
        $profile = $version->profile;
        if ($overrides === null || $profile === null || $profile->is_default) {
            return $version;
        }

        $organization = Organization::query()->withoutGlobalScopes()->find($version->organization_id);
        $base = $organization !== null ? $this->baseVersionFor($organization) : null;
        if ($base === null || (int) $base->document_render_profile_id === (int) $profile->id) {
            return $version;
        }

        $pick = fn (string $section) => in_array($section, $overrides, true);

        return $this->detachedCopy($version, [
            'layout' => $pick('layout') ? $version->layout : $base->layout,
            'block_rules' => $pick('block_rules') ? $version->block_rules : $base->block_rules,
            'table_style' => $pick('table_style') ? $version->table_style : $base->table_style,
            'content_texts' => $pick('content_texts') ? $version->content_texts : $base->content_texts,
            'first_asset_id' => $pick('assets') ? $version->first_asset_id : $base->first_asset_id,
            'following_asset_id' => $pick('assets') ? $version->following_asset_id : $base->following_asset_id,
        ]);
    }

    /**
     * Branding-Referenz (#83): trägt eine Version `use_brand_colors`, werden
     * Akzent- und Kopfzeilenfarbe beim Rendern/Preflight aus dem
     * Organisationsbranding aufgelöst — Single Source of Truth bleibt der
     * {@see \App\Services\BrandingService}-Datenstand, es entsteht keine
     * Farbkopie im Profil.
     */
    private function withResolvedBrandColors(DocumentRenderProfileVersion $version): DocumentRenderProfileVersion {
        $tableStyle = (array) $version->table_style;
        if (empty($tableStyle['use_brand_colors'])) {
            return $version;
        }

        $organization = Organization::query()->withoutGlobalScopes()->find($version->organization_id);
        $colors = (array) (($organization?->brandingSettings() ?? [])['colors'] ?? []);
        $primary = $this->hexOrNull($colors['primary'] ?? null);
        $accent = $this->hexOrNull($colors['accent'] ?? null);
        if ($primary === null && $accent === null) {
            return $version;
        }

        $overrides = (array) ($tableStyle['overrides'] ?? []);
        $overrides['accent_color'] = $accent ?? $primary;
        if ($primary !== null) {
            $overrides['header_fill'] = $primary;
        }
        $tableStyle['overrides'] = $overrides;

        $copy = $version->exists ? $this->detachedCopy($version, []) : $version;
        $copy->table_style = $tableStyle;

        return $copy;
    }

    /**
     * Nicht persistierte Arbeitskopie einer Version (effektiver Stand) —
     * niemals speichern; nur Eingabe für Preflight und Payload-Aufbau.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function detachedCopy(DocumentRenderProfileVersion $version, array $attributes): DocumentRenderProfileVersion {
        $copy = $version->newInstance([
            'organization_id' => $version->organization_id,
            'document_render_profile_id' => $version->document_render_profile_id,
            'version' => $version->version,
            'status' => $version->status,
            'layout' => $version->layout,
            'block_rules' => $version->block_rules,
            'table_style' => $version->table_style,
            'content_texts' => $version->content_texts,
            'first_asset_id' => $version->first_asset_id,
            'following_asset_id' => $version->following_asset_id,
        ]);
        // $attributes gewinnen über die kopierten Grundwerte.
        foreach ($attributes as $key => $value) {
            $copy->{$key} = $value;
        }
        $copy->id = $version->id;
        $copy->exists = false;
        if ($version->relationLoaded('profile') || $version->profile !== null) {
            $copy->setRelation('profile', $version->profile);
        }

        return $copy;
    }

    /**
     * Kopf-/Fußtexte (MVP-651): reiner Text, längenbegrenzt — kein HTML.
     *
     * @return array{header_text: ?string, footer_text: ?string}|null
     */
    private function sanitizeContentTexts(mixed $raw): ?array {
        if ($raw === null) {
            return null;
        }
        $raw = (array) $raw;
        $clean = [];
        foreach (['header_text', 'footer_text'] as $key) {
            $value = trim(strip_tags((string) ($raw[$key] ?? '')));
            $clean[$key] = $value === '' ? null : mb_substr($value, 0, 2000);
        }

        return $clean['header_text'] === null && $clean['footer_text'] === null ? null : $clean;
    }

    private function hexOrNull(mixed $value): ?string {
        $value = trim((string) $value);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : null;
    }

    /**
     * Preflight des aktuellen Standes einer Version — gegen den effektiven
     * Stand (Basisdesign + Overrides) und den vollen Prüfumfang des Profils
     * (Arten, Familie, Basisdesign = alle brandfähigen Arten).
     */
    public function preflightFor(DocumentRenderProfileVersion $version): PreflightResult {
        $profile = $version->profile;
        if ($profile === null) {
            throw new RuntimeException('Profilversion ohne Profil.');
        }

        return $this->preflight->check($this->effectiveVersion($version), $this->preflightKinds($profile));
    }

    /** Familien-Bindung einer Variante setzen/entfernen (#83). */
    public function assignFamily(DocumentRenderProfile $profile, ?RenderDocumentFamily $family): void {
        $profile->document_family = $family;
        $profile->save();
    }

    /** Sektionen, die eine erbende Variante überschreiben kann. */
    public const OVERRIDE_SECTIONS = ['layout', 'assets', 'block_rules', 'table_style', 'content_texts'];

    /** @return array<int, string>|null */
    private function sanitizeOverrideSections(mixed $raw): ?array {
        if ($raw === null) {
            return null;
        }

        return array_values(array_intersect(self::OVERRIDE_SECTIONS, array_map('strval', (array) $raw)));
    }

    /**
     * Freigegebene, PDF-fähige Schriftfamilien (#83): dompdf bündelt DejaVu —
     * kuratierte Liste statt freier Font-Eingabe. Schlüssel → CSS-Name.
     *
     * @var array<string, string>
     */
    public const FONT_FAMILIES = [
        'dejavu-sans' => 'DejaVu Sans',
        'dejavu-serif' => 'DejaVu Serif',
        'dejavu-mono' => 'DejaVu Sans Mono',
    ];

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
            // null = Systemstandard der jeweiligen Dokument-View (#83).
            'typography' => ['font_family' => null, 'base_size_pt' => null],
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
        // Typografie (#83): nur kuratierte Schriften und sichere Grundgrößen.
        $font = (string) ($layout['typography']['font_family'] ?? '');
        $size = $layout['typography']['base_size_pt'] ?? null;
        $clean['typography'] = [
            'font_family' => array_key_exists($font, self::FONT_FAMILIES) ? $font : null,
            'base_size_pt' => is_numeric($size) ? max(8.0, min(14.0, round((float) $size, 1))) : null,
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

        return [
            'preset' => $preset->value,
            'overrides' => $overrides,
            // #83: Farben aus dem Organisationsbranding REFERENZIEREN statt
            // kopieren — die Auflösung passiert beim Rendern/Preflight.
            'use_brand_colors' => (bool) ($style['use_brand_colors'] ?? false),
        ];
    }

    private function mm(mixed $value): float {
        return round(max(0.0, min(297.0, (float) $value)), 1);
    }
}
