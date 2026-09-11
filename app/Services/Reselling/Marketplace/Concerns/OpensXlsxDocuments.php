<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpensXlsxDocuments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace\Concerns;

use CommonToolkit\Entities\XLSX\Document;
use CommonToolkit\Parsers\XLSXDocumentParser;
use RuntimeException;
use Throwable;

/**
 * XLSX-Exporte mit Grenzen öffnen (Toolkit v1.32): entpackte Größe (Toolkit-
 * Vorgabe 256 MiB) und Datenzeilen je Blatt. Überschreitung und jeder andere
 * Parserfehler werden zur übersetzten Meldung ohne Serverpfad. Das Toolkit
 * wirft für beide Grenzen dieselbe RuntimeException wie für defekte Dateien —
 * erkannt wird sie am Wortlaut.
 */
trait OpensXlsxDocuments {
    /** Abo-, Vertrags- und Preislisten haben Hunderte Zeilen, keine Zehntausende. */
    protected const XLSX_MAX_ROWS = 50_000;

    private const XLSX_LIMIT_PATTERN = '/überschreitet die erlaubte (?:entpackte Größe|Zeilenzahl)/u';

    /**
     * @param  string  $unreadableKey  Übersetzungsschlüssel mit :file und :reason für sonstige Parserfehler
     */
    protected static function openXlsx(string $file, int $maxRows, string $unreadableKey): Document {
        $name = basename($file);
        try {
            return XLSXDocumentParser::fromFile($file, true, maxRows: $maxRows);
        } catch (Throwable $e) {
            if (preg_match(self::XLSX_LIMIT_PATTERN, $e->getMessage()) === 1) {
                throw new RuntimeException((string) __('resale_import.file.too_large', ['file' => $name, 'rows' => $maxRows, 'mb' => intdiv(XLSXDocumentParser::DEFAULT_MAX_UNCOMPRESSED_BYTES, 1024 * 1024)]), 0, $e);
            }

            throw new RuntimeException((string) __($unreadableKey, ['file' => $name, 'reason' => str_replace($file, $name, $e->getMessage())]), 0, $e);
        }
    }
}
