<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullDocumentVersionImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror\Contracts;

use App\Models\Document\{Document, DocumentVersion};
use App\Models\Platform\User;
use App\Modules\ModuleUnavailableException;

final class NullDocumentVersionImporter implements DocumentVersionImporter {
    public function addVersionFromContents(Document $document, User $actor, string $contents, string $originalName, ?string $mime = null, ?string $note = null, string $source = 'webdav-import'): DocumentVersion {
        throw ModuleUnavailableException::for('module.documents');
    }
}
