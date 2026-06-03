<?php
/*
 * Created on   : Tue Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FolderWorkspaceSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Sources;

/**
 * Workspace-Quelle aus einem Toggl-Export-Ordner. Dünner Adapter um den
 * {@see TogglWorkspaceReader}, der den Workspace-Pfad bindet.
 */
final class FolderWorkspaceSource implements WorkspaceSourceInterface {
    public function __construct(
        private readonly string $workspacePath,
        private readonly TogglWorkspaceReader $reader = new TogglWorkspaceReader,
    ) {}

    public function clients(): array {
        return $this->reader->clients($this->workspacePath);
    }

    public function projects(): array {
        return $this->reader->projects($this->workspacePath);
    }

    public function users(): array {
        return $this->reader->users($this->workspacePath);
    }

    public function entries(): array {
        return $this->reader->entries($this->workspacePath);
    }
}
