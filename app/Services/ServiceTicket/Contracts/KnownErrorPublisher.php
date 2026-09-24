<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnownErrorPublisher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Contracts;

use App\Models\Knowledge\KnowledgeArticle;
use App\Models\Platform\User;
use App\Models\ServiceTicket\Problem;

/**
 * Known Error als Wissensartikel veröffentlichen (Welle 4.5): definiert vom
 * Helpdesk, gebunden vom Wissensmodul. Null-Bindung: Wissensmodul fehlt.
 */
interface KnownErrorPublisher {
    /** Legt den Artikel an und ordnet ihn der Sammlung „Known Errors" zu. */
    public function publish(Problem $problem, User $actor): KnowledgeArticle;
}
