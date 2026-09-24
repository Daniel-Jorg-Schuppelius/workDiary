<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullKnownErrorPublisher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Contracts;

use App\Models\Knowledge\KnowledgeArticle;
use App\Models\Platform\User;
use App\Models\ServiceTicket\Problem;
use App\Modules\ModuleUnavailableException;

final class NullKnownErrorPublisher implements KnownErrorPublisher {
    public function publish(Problem $problem, User $actor): KnowledgeArticle {
        throw ModuleUnavailableException::for('module.knowledge');
    }
}
