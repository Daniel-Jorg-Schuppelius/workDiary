<?php

namespace App\Plugins\Contracts;

/**
 * Base contract for every plugin shipped with or added to workDiary.
 * A plugin is a thin glue layer between an external service (e.g. Lexoffice)
 * and the application's domain models.
 *
 * Implementations should be stateless and resolved through the container.
 */
interface Plugin {
    /** Unique stable identifier (e.g. "lexoffice"). Used for config + DB refs. */
    public function id(): string;

    /** Human-friendly name (UI label). */
    public function name(): string;

    /** Semantic version string of the plugin implementation. */
    public function version(): string;

    /** Short description for the plugin admin overview. */
    public function description(): string;

    /** True if the plugin is configured and ready to be invoked. */
    public function isEnabled(): bool;

    /**
     * Capability identifiers this plugin advertises.
     *
     * @return array<int, string>
     */
    public function capabilities(): array;

    /**
     * Optional admin/settings panel descriptor. Return null to hide.
     *
     * @return array{route?: string, label?: string, icon?: string}|null
     */
    public function adminPanel(): ?array;
}
