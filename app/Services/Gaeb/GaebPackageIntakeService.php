<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebPackageIntakeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\{GaebImportStatus, GaebPhase};
use App\Models\Applications\ApplicationOpportunity;
use App\Models\{GaebImport, User};
use App\Services\Document\DocumentService;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use ERechnungToolkit\Enums\GaebFormat;
use ERechnungToolkit\Helper\Gaeb\GaebFormatDetector;
use Exception;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Paketeingang für Vergabeunterlagen (Feature 108, MVP-627).
 *
 * Vergabeunterlagen kommen als **Paket**: ein ZIP mit Leistungsverzeichnis,
 * Bewerbungsbedingungen, Plänen und Vordrucken. Der Eingang zerlegt es und
 * trennt zwei Sorten Inhalt:
 *
 * - **GAEB-Dateien** werden als *Vorschlag* abgelegt (`GaebImport` im Zustand
 *   `pending`), nicht importiert. Ein Leistungsverzeichnis blind in die
 *   Datenbank zu schreiben, hieße, es ungeprüft zu übernehmen — und ein
 *   Paket kann mehrere Lose enthalten, von denen nur eines interessiert.
 *   Inbox-First ist hier keine Vorsichtsmaßnahme, sondern die Sachlage.
 * - **Alles andere** geht über den {@see DocumentService} ins DMS und hängt
 *   am Vergabevorgang. Was zur Ausschreibung gehört, gehört an die Akte.
 *
 * **Die Formatfamilie kommt aus dem Inhalt, nicht aus der Endung.** Ein
 * Vergabeportal benennt dieselbe Datei je nach Werkzeug `.x83`, `.X83` oder
 * `.zip.x83`; der Detektor des Toolkits liest die ersten Bytes.
 *
 * Doppelte Dateien werden am Hash erkannt: Dasselbe Paket zweimal einzuspielen
 * darf keine zweiten Vorschläge erzeugen — Portale stellen Unterlagen bei
 * jeder Berichtigung erneut bereit.
 */
final class GaebPackageIntakeService {
    /** Verzeichnis der abgelegten Paketdateien in der privaten Ablage. */
    private const STORAGE_PREFIX = 'gaeb-packages';

    /** Was ein Paket an Einzeldateien enthalten darf, bevor abgebrochen wird. */
    private const MAX_ENTRIES = 500;

    /** Obergrenze je Eintrag (entpackt) — GAEB-XML bleibt weit darunter. */
    private const MAX_ENTRY_BYTES = 64 * 1024 * 1024;

    /** Obergrenze der Summe aller entpackten Einträge. */
    private const MAX_UNCOMPRESSED_BYTES = 256 * 1024 * 1024;

    public function __construct(
        private readonly DocumentService $documents,
        private readonly GaebFormatDetector $detector = new GaebFormatDetector,
    ) {}

    /**
     * Nimmt ein Paket (ZIP) oder eine Einzeldatei entgegen.
     *
     * @return array{gaeb: list<GaebImport>, documents: int, skipped: int}
     */
    public function intake(
        string $contents,
        string $filename,
        int $organizationId,
        User $actor,
        ?ApplicationOpportunity $opportunity = null,
    ): array {
        $entries = $this->isZip($contents)
            ? $this->unpack($contents)
            : [['name' => $filename, 'contents' => $contents]];

        $result = ['gaeb' => [], 'documents' => 0, 'skipped' => 0];
        $packageName = $this->isZip($contents) ? $filename : null;

        foreach ($entries as $entry) {
            $format = $this->detector->format($entry['contents']);

            if ($format !== GaebFormat::Unknown) {
                $import = $this->proposeImport($entry, $format, $organizationId, $actor, $opportunity, $packageName);
                if ($import === null) {
                    $result['skipped']++;

                    continue;
                }
                $result['gaeb'][] = $import;

                continue;
            }

            if ($opportunity !== null) {
                $this->storeDocument($entry, $actor, $opportunity);
                $result['documents']++;
            } else {
                // Ohne Vorgang fehlt der Akte-Bezug; die Datei blind ins DMS zu
                // legen, machte sie unauffindbar.
                $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * Legt einen GAEB-Vorschlag ab. Gibt `null` zurück, wenn dieselbe Datei
     * bereits als Vorschlag oder Import vorliegt.
     *
     * @param array{name: string, contents: string} $entry
     */
    private function proposeImport(
        array $entry,
        GaebFormat $format,
        int $organizationId,
        User $actor,
        ?ApplicationOpportunity $opportunity,
        ?string $packageName,
    ): ?GaebImport {
        // Der Hash beschreibt die Datei, wie sie hereinkam — dieselbe Regel
        // wie beim Einzelimport, damit beide Wege denselben Hash liefern.
        $hash = CryptoHelper::hash($entry['contents']);

        $exists = GaebImport::query()
            ->where('organization_id', $organizationId)
            ->where('file_hash', $hash)
            ->exists();
        if ($exists) {
            return null;
        }

        $detected = $this->detector->detect($entry['contents'], $entry['name']);
        $path = self::STORAGE_PREFIX . '/' . $organizationId . '/' . $hash . '-' . basename($entry['name']);
        Storage::disk('local')->put($path, $entry['contents']);

        return GaebImport::query()->create([
            'organization_id' => $organizationId,
            'application_opportunity_id' => $opportunity?->id,
            'filename' => basename($entry['name']),
            'file_hash' => $hash,
            'stored_path' => $path,
            'package_name' => $packageName,
            'source_format' => $format->value,
            // Die App führt ihr eigenes Phasen-Enum; das Toolkit-Enum daneben
            // zu casten, schlüge fehl.
            'phase' => GaebPhase::fromCode($detected['phaseCode']),
            // Vorschlag, kein Import: Was importiert wird, entscheidet ein
            // Mensch.
            'status' => GaebImportStatus::Pending,
            'created_by' => $actor->id,
        ]);
    }

    /** @param array{name: string, contents: string} $entry */
    private function storeDocument(array $entry, User $actor, ApplicationOpportunity $opportunity): void {
        $this->documents->createFromContents(
            $opportunity,
            $actor,
            [
                'title' => basename($entry['name']),
                'document_type' => 'other',
                'description' => (string) __('Aus den Vergabeunterlagen übernommen.'),
            ],
            $entry['contents'],
            basename($entry['name']),
        );
    }

    /** ZIP-Signatur: „PK\x03\x04" steht am Anfang jedes Archivs. */
    private function isZip(string $contents): bool {
        return str_starts_with($contents, "PK\x03\x04");
    }

    /**
     * Entpackt das Archiv über das Common-Toolkit (Vollscan 2026-08-23, C6).
     *
     * {@see ZipFile::readEntries()} bringt den harten Zip-Slip-Guard und den
     * ZIP-Bomb-Guard (deklarierte Größe wird VOR dem Entpacken geprüft) mit:
     * Ein Archiv aus fremder Hand darf weder Pfade bestimmen noch Gigabytes
     * in den Speicher entfalten. Ein unsicherer Eintragspfad verwirft das
     * ganze Paket — ein manipuliertes Paket ist als Ganzes nicht vertrauenswürdig.
     *
     * @return list<array{name: string, contents: string}>
     */
    private function unpack(string $contents): array {
        try {
            $raw = ZipFile::readEntries($contents, maxBytes: self::MAX_UNCOMPRESSED_BYTES);
        } catch (InvalidArgumentException $e) {
            // Byte-Limit → bestehende Größen-Meldung; alles andere ist ein
            // unsicherer Eintragspfad (harter Zip-Slip-Guard des Toolkits).
            throw new RuntimeException((string) (str_contains($e->getMessage(), 'Byte-Limit')
                ? __('Das Paket überschreitet die zulässige entpackte Größe.')
                : __('Das Paket enthält unsichere Dateipfade.')), previous: $e);
        } catch (Exception $e) {
            throw new RuntimeException((string) __('Das Paket ließ sich nicht öffnen.'), previous: $e);
        }

        // Entry-Limit app-seitig: so bleibt die Nutzer-Meldung mit der echten
        // Anzahl erhalten (das Toolkit kennt sie beim Abbruch nicht); der
        // Speicher ist durch maxBytes bereits gedeckelt.
        if (count($raw) > self::MAX_ENTRIES) {
            throw new RuntimeException((string) __('Das Paket enthält :count Dateien — mehr als :max.', ['count' => count($raw), 'max' => self::MAX_ENTRIES]));
        }

        $entries = [];
        foreach ($raw as $name => $entryContents) {
            if ($entryContents === '') {
                continue;
            }
            if (strlen($entryContents) > self::MAX_ENTRY_BYTES) {
                throw new RuntimeException((string) __('Das Paket überschreitet die zulässige entpackte Größe.'));
            }
            $entries[] = ['name' => $name, 'contents' => $entryContents];
        }

        return $entries;
    }
}
