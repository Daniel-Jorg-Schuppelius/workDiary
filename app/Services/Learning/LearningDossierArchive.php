<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningDossierArchive.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\LearningCertificate;
use App\Models\Platform\{Organization, User};
use App\Support\CsvExport;
use App\Support\Query\DateRange;
use CommonToolkit\Enums\Common\CSV\QuotingStyle;
use CommonToolkit\Helper\Data\CSV\StringHelper;
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Validation\ValidationException;

/**
 * Nachweismappe als ZIP (Feature 149, MVP-1600): die Mappe als PDF und JSON,
 * die Zertifikate als Einzelbelege und ein Index, der jede Zeile der Mappe
 * ihrer Datei zuordnet. Immer namentlich — der Aufrufer prüft den Anlass.
 */
class LearningDossierArchive {
    /** Jedes Zertifikat wird einzeln gerendert — darüber wird die Auswahl eingegrenzt. */
    public const int MAX_CERTIFICATES = 300;

    public function __construct(
        private readonly QualificationDossierService $dossier,
        private readonly LearningDossierPdfRenderer $pdf,
        private readonly LearningCertificatePdfRenderer $certificates,
    ) {}

    /**
     * @param  Collection<int, User>  $users
     * @return string ZIP als Binärstring
     */
    public function build(Organization $organization, Collection $users, Carbon $from, Carbon $to, string $reason): string {
        $certificates = LearningCertificate::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->where('issued_on', '<', DateRange::dayAfter($to))
            ->orderBy('number')
            ->get()
            ->keyBy('number');

        if ($certificates->count() > self::MAX_CERTIFICATES) {
            throw ValidationException::withMessages([
                'selection' => (string) __('learning.errors.dossier_archive_too_large', ['max' => self::MAX_CERTIFICATES]),
            ]);
        }

        $entries = [
            'nachweismappe.pdf' => $this->pdf->output($organization, $users, $from, true, $reason, $to),
            'nachweismappe.json' => JsonHelper::encode(
                $this->dossier->exportPayload($organization, $users, $from, $to),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ),
        ];

        $index = [[
            (string) __('learning.field.person'),
            (string) __('learning.field.proof_kind'),
            (string) __('learning.field.title'),
            (string) __('learning.field.certificate_number'),
            (string) __('learning.field.issued_on'),
            (string) __('learning.field.valid_until'),
            (string) __('learning.field.coverage'),
            (string) __('learning.field.file'),
        ]];

        foreach ($this->dossier->forUsers($users, $from, $to) as $row) {
            $person = (string) $row['user']->name;
            foreach ($row['qualifications'] as $entry) {
                $index[] = [$person, (string) __('learning.field.qualifications'), $entry['name'], '', $entry['valid_from'], $entry['valid_until'], $entry['coverage'], ''];
            }
            foreach ($row['instructions'] as $entry) {
                $index[] = [$person, (string) __('learning.field.instructions'), $entry['topic'], '', $entry['held_on'], $entry['next_due_on'], $entry['coverage'], ''];
            }
            foreach ($row['certificates'] as $entry) {
                $certificate = $certificates->get($entry['number']);
                $file = '';
                if ($certificate !== null) {
                    $file = 'zertifikate/' . $this->certificates->filename($certificate);
                    $entries[$file] = $this->certificates->output($certificate);
                }
                $index[] = [$person, (string) __('learning.field.certificates'), $entry['course'], $entry['number'], $entry['issued_on'], $entry['valid_until'], $entry['coverage'], $file];
            }
        }

        $csv = '';
        foreach ($index as $line) {
            $csv .= StringHelper::encodeLine(CsvExport::guardRow(array_map(static fn (mixed $cell): string => (string) $cell, $line)), ';', '"', QuotingStyle::FPUTCSV) . "\n";
        }
        // BOM: Tabellenprogramme lesen Umlaute sonst als Latin-1.
        $entries['index.csv'] = "\xEF\xBB\xBF" . $csv;

        return ZipFile::createFromStrings($entries);
    }
}
