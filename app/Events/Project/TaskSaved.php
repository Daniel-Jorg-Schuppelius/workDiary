<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskSaved.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Project;

use App\Models\Project\Task;
use Illuminate\Foundation\Events\Dispatchable;

/** Aufgabe gespeichert — Boards ziehen ihre Spalte nach (synchron, wie der frühere Observer). */
final class TaskSaved {
    use Dispatchable;

    public function __construct(public readonly Task $task) {}
}
