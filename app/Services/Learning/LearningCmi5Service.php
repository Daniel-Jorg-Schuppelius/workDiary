<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCmi5Service.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{LearningCmi5Package, LearningUnit};
use App\Models\User;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\Helper\FileSystem\{File, Folder};
use ELearningToolkit\Cmi5\{AssignableUnit, Block, Cmi5Exception, CourseStructure, LanguageMap};
use ELearningToolkit\Package\{ExtractedPackage, PackageException, PackageExtractor};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * cmi5-Kurse importieren (Feature 149) — einzige Schreibstelle für Paket und AUs.
 *
 * Zwei Formen: ein ZIP mit `cmi5.xml` (die AUs liegen im Paket) oder eine einzelne
 * `cmi5.xml`, deren AUs extern liegen. Relative Adressen gelten relativ zum Ort der
 * `cmi5.xml`. Die Aktivitäts-ID jeder AU vergibt das LMS selbst (cmi5 9.4); die
 * Kennung des Herausgebers bleibt daneben erhalten.
 */
class LearningCmi5Service {
    public function __construct(private readonly PackageExtractor $extractor) {}

    public function import(LearningUnit $unit, string $filePath, string $originalName, ?User $actor = null): LearningCmi5Package {
        $isStructureOnly = str_ends_with(strtolower($originalName), '.xml');
        $relative = null;
        $absolute = null;
        $extracted = null;

        try {
            if ($isStructureOnly) {
                $xml = File::read($filePath);
            } else {
                $relative = 'learning/cmi5/' . $unit->organization_id . '/' . Str::lower(Str::random(16));
                $absolute = storage_path('app/' . $relative);
                $extracted = $this->extractor->extract($filePath, $absolute, PackageExtractor::CMI5_DESCRIPTOR);
                $xml = $extracted->descriptorXml;
            }

            $structure = CourseStructure::fromXml($xml);
        } catch (PackageException|Cmi5Exception $e) {
            if ($absolute !== null) {
                try {
                    if (Folder::exists($absolute)) {
                        Folder::delete($absolute, true);
                    }
                } catch (\Throwable) {
                    // Best effort wie zuvor (symlink-sicher, Links werden nur entfernt).
                }
            }

            // Das Toolkit bleibt sprachneutral — übersetzt wird hier.
            throw ValidationException::withMessages(['package' => (string) __('learning.errors.cmi5.' . $e->reason)]);
        }

        foreach ($structure->assignableUnits() as $au) {
            if (! $au->hasAbsoluteUrl() && $extracted === null) {
                throw ValidationException::withMessages(['package' => (string) __('learning.errors.cmi5.relative_url_without_package')]);
            }
        }

        $obsolete = LearningCmi5Package::query()->where('learning_unit_id', $unit->id)->pluck('storage_path')->all();
        $locale = app()->getLocale();

        $package = DB::transaction(function () use ($unit, $structure, $xml, $extracted, $relative, $actor, $locale): LearningCmi5Package {
            // Ein Ersatzkurs löst den alten samt Registrierungen ab: Die AUs bekommen
            // neue Aktivitäts-IDs, alte Sitzungen könnten nichts mehr zuordnen.
            LearningCmi5Package::query()->where('learning_unit_id', $unit->id)->delete();

            $title = LanguageMap::pick($structure->title, $locale, 'de', 'en');

            $package = LearningCmi5Package::query()->create([
                'organization_id' => $unit->organization_id,
                'learning_unit_id' => $unit->id,
                'title' => mb_substr($title !== '' ? $title : $unit->title, 0, 255),
                'course_id' => mb_substr($structure->courseId, 0, 500),
                'activity_id' => $this->activityId(),
                'blocks' => $this->blocks($structure, $locale),
                'storage_path' => $relative,
                'structure_hash' => (string) CryptoHelper::hash($xml),
                'file_count' => $extracted->files ?? 0,
                'size_bytes' => $extracted->bytes ?? 0,
                'uploaded_by_user_id' => $actor?->id,
            ]);

            foreach ($structure->assignableUnits() as $position => $au) {
                $package->units()->create([
                    'organization_id' => $unit->organization_id,
                    'publisher_id' => $au->id,
                    'activity_id' => $this->activityId(),
                    'title' => mb_substr(LanguageMap::pick($au->title, $locale, 'de', 'en'), 0, 255),
                    'url' => $this->resolveUrl($au->url, $au->hasAbsoluteUrl(), $extracted),
                    'move_on' => $au->moveOn->value,
                    'mastery_score' => $au->masteryScore,
                    'launch_method' => $au->launchMethod->value,
                    'launch_parameters' => $au->launchParameters,
                    'entitlement_key' => $au->entitlementKey,
                    'position' => $position,
                ]);
            }

            return $package;
        });

        // Erst nach dem Commit: Ein Rollback hätte die alten Dateien noch gebraucht.
        foreach ($obsolete as $path) {
            if (is_string($path) && $path !== '' && $path !== $relative) {
                try {
                    if (Folder::exists(storage_path('app/' . $path))) {
                        Folder::delete(storage_path('app/' . $path), true);
                    }
                } catch (\Throwable) {
                    // Best effort wie zuvor (symlink-sicher, Links werden nur entfernt).
                }
            }
        }

        return $package;
    }

    /** Aktivitäts-ID aus Sicht des LMS (cmi5 9.4) — nie die des Herausgebers. */
    private function activityId(): string {
        return 'urn:uuid:' . Str::uuid()->toString();
    }

    /**
     * Blöcke samt eigener Aktivitäts-ID und den Herausgeber-Kennungen aller AUs darunter.
     *
     * @return list<array{publisher_id: string, activity_id: string, title: string, units: list<string>}>
     */
    private function blocks(CourseStructure $structure, string $locale): array {
        return array_map(fn (Block $block): array => [
            'publisher_id' => $block->id,
            'activity_id' => $this->activityId(),
            'title' => mb_substr(LanguageMap::pick($block->title, $locale, 'de', 'en'), 0, 255),
            'units' => array_map(static fn (AssignableUnit $au): string => $au->id, $block->assignableUnits()),
        ], $structure->blocks());
    }

    private function resolveUrl(string $url, bool $absolute, ?ExtractedPackage $extracted): string {
        if ($absolute || $extracted === null) {
            return $url;
        }

        return $extracted->resolve($url);
    }
}
