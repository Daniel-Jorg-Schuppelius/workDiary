<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourStarted.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Tour;

use App\Models\Diary\Tour;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Eine Tour wurde gestartet (MVP-1181): Folgeprozesse wie temporäre Zonen hängen sich hier an. */
final class TourStarted implements ShouldDispatchAfterCommit {
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Tour $tour) {}
}
