<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentRelations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\View\Components\Billing;

use App\Enums\Billing\DocumentRelationKind;
use App\Services\Billing\DocumentRelations as DocumentRelationsService;
use App\Services\Billing\Dto\DocumentNode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;

/** „Zugehörige Belege“ an Rechnung, Angebot, Fertigungsauftrag und Übergabe (MVP-1159). */
final class DocumentRelations extends Component {
    /** @var list<array{node: DocumentNode, kind: DocumentRelationKind, via: ?DocumentNode}> */
    public array $relations;

    /** Abhängigkeiten stehen vor den Attributen (Laravel löst sie aus dem Container). */
    public function __construct(DocumentRelationsService $relations, Model $document) {
        $this->relations = $relations->of($document);
    }

    public function shouldRender(): bool {
        return $this->relations !== [];
    }

    public function render(): View {
        return view('components.billing.document-relations');
    }
}
