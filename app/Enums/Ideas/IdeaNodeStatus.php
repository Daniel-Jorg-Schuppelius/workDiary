<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNodeStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ideas;

/**
 * Bearbeitungsstatus eines Knotens (Feature 054, MVP-135): kleiner,
 * semantischer Workflow von der Idee zur Entscheidung. Bewusst als Dropdown
 * im Editor statt Freitext, damit Auswertung/Filter eindeutig bleiben. Die
 * Spalte `idea_nodes.node_status` bleibt ein String (Rückwärtskompatibilität
 * für Altbestände); die Enum-Werte sind ≤ 24 Zeichen.
 */
enum IdeaNodeStatus: string {
    case Open = 'open';
    case InReview = 'in_review';
    case Decided = 'decided';
    case Rejected = 'rejected';
    case Done = 'done';

    public function label(): string {
        return __('ideas.node_status.' . $this->value);
    }
}
