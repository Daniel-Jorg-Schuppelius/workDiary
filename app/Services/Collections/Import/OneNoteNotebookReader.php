<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OneNoteNotebookReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections\Import;

use App\Plugins\Msgraph\Api\MsgraphOneNoteClient;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * Liest ein OneNote-Notizbuch für die Übernahme (MVP-815, Feature 155):
 * Abschnittsgruppe und Abschnitt werden Sammlungen, jede Seite ein Dokument.
 * Der Seiteninhalt ist HTML und wird zu Text — die Felder von Notiz und Artikel
 * sind Text, eingebettetes HTML käme nie ungeprüft in die Oberfläche.
 */
class OneNoteNotebookReader {
    /**
     * Schon übernommene Seiten (`$known`) werden nicht erneut geladen.
     *
     * @param  callable(string): bool  $known
     * @return array{documents: list<ImportedDocument>, limited: bool}
     */
    public function documents(MsgraphOneNoteClient $client, string $notebookId, string $notebookName, int $limit, callable $known): array {
        $documents = [];
        $downloaded = 0;
        foreach ($client->sections($notebookId) as $section) {
            $folders = array_values(array_filter([$section['group'], $section['name']], static fn (?string $name): bool => $name !== null && trim($name) !== ''));
            foreach ($client->pages($section['id']) as $page) {
                $isKnown = $known($page['id']);
                if (! $isKnown && $downloaded >= $limit) {
                    return ['documents' => $documents, 'limited' => true];
                }
                $documents[] = new ImportedDocument(
                    externalId: $page['id'],
                    title: $page['title'],
                    text: $isKnown ? '' : $this->text($client->pageContent($page['id'])),
                    folders: $folders,
                    names: [$page['title']],
                    modifiedAt: $page['modified'] !== null ? CarbonImmutable::parse($page['modified']) : null,
                    sourceLabel: 'OneNote · ' . implode(' › ', [$notebookName, ...$folders, $page['title']]),
                );
                $downloaded += $isKnown ? 0 : 1;
            }
        }

        return ['documents' => $documents, 'limited' => false];
    }

    /** Seiten-HTML als lesbarer Text: Absätze und Listenpunkte bleiben Zeilen. */
    public function text(string $html): string {
        $html = preg_replace('#<(script|style|head)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<li\b[^>]*>#i', "\n- ", $html) ?? $html;
        $html = preg_replace('#<br\s*/?>|</(p|div|h[1-6]|tr|table|ul|ol)>#i', "\n", $html) ?? $html;
        $text = StringHelper::htmlEntitiesToText(strip_tags($html));

        $lines = array_map(static fn (string $line): string => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line) ?? $line), explode("\n", $text));

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) ?? '');
    }
}
