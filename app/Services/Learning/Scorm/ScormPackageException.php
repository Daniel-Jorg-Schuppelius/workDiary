<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormPackageException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\Scorm;

use RuntimeException;

/**
 * Fehler beim Entpacken oder Lesen eines SCORM-Pakets (Feature 149).
 *
 * Trägt einen **maschinellen Grund** statt einer fertigen Meldung: der
 * Parser bleibt sprachneutral (und damit toolkit-fähig), die Übersetzung
 * passiert erst in der Anwendungsschicht.
 */
class ScormPackageException extends RuntimeException {
    public const UNREADABLE = 'unreadable';
    public const TOO_MANY_FILES = 'too_many_files';
    public const TOO_LARGE = 'too_large';
    public const TARGET_UNWRITABLE = 'target_unwritable';
    public const MANIFEST_MISSING = 'manifest_missing';
    public const PATH_ESCAPE = 'path_escape';
    public const EMPTY_NAME = 'empty_name';

    public function __construct(public readonly string $reason, string $message = '') {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
