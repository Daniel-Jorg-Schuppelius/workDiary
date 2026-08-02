<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingsField.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use InvalidArgumentException;

/**
 * Typisiertes Feld des Plugin-Settings-Schemas (Review 2026-08, W5b).
 *
 * Plugins können `settingsSchema()` weiterhin als Array-Literale liefern
 * (BC); der Admin-Controller normalisiert beides über {@see fromArray()}.
 * Neue Plugins deklarieren bevorzugt:
 *
 *     SettingsField::password('api_key', 'API-Key', required: true)->toArray()
 *
 * `secret` maskiert das Feld unabhängig vom Typ (Default: true bei password);
 * `required` wird für ALLE Typen durchgesetzt (vorher nur password, F2).
 */
final class SettingsField {
    /**
     * @param  array<string, string>  $options
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly FieldType $type = FieldType::Text,
        public readonly bool $required = false,
        public readonly ?bool $secret = null,
        public readonly mixed $default = null,
        public readonly array $options = [],
        public readonly ?string $help = null,
    ) {
        if ($key === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException(sprintf('Ungültiger Settings-Key "%s" (erwartet ^[a-z][a-z0-9_]*$).', $key));
        }
        if ($label === '') {
            throw new InvalidArgumentException(sprintf('Feld "%s" braucht ein Label.', $key));
        }
        if ($type === FieldType::Select && $options === []) {
            throw new InvalidArgumentException(sprintf('Select-Feld "%s" braucht options.', $key));
        }
    }

    public static function text(string $key, string $label, bool $required = false, ?string $help = null, mixed $default = null): self {
        return new self($key, $label, FieldType::Text, $required, help: $help, default: $default);
    }

    public static function password(string $key, string $label, bool $required = false, ?string $help = null): self {
        return new self($key, $label, FieldType::Password, $required, secret: true, help: $help);
    }

    public static function boolean(string $key, string $label, bool $default = false, ?string $help = null): self {
        return new self($key, $label, FieldType::Boolean, default: $default, help: $help);
    }

    /** @param array<string, string> $options */
    public static function select(string $key, string $label, array $options, bool $required = false, mixed $default = null, ?string $help = null): self {
        return new self($key, $label, FieldType::Select, $required, default: $default, options: $options, help: $help);
    }

    public static function number(string $key, string $label, bool $required = false, mixed $default = null, ?string $help = null): self {
        return new self($key, $label, FieldType::Number, $required, default: $default, help: $help);
    }

    public static function url(string $key, string $label, bool $required = false, ?string $help = null): self {
        return new self($key, $label, FieldType::Url, $required, help: $help);
    }

    public static function textarea(string $key, string $label, bool $required = false, ?string $help = null): self {
        return new self($key, $label, FieldType::Textarea, $required, help: $help);
    }

    public function isSecret(): bool {
        return $this->secret ?? ($this->type === FieldType::Password);
    }

    /**
     * Normalisiert ein Schema-Feld (Array-Literal ODER SettingsField) —
     * zentrale Stelle, an der Tippfehler in Keys zur Ausnahme werden statt
     * still zu verschwinden.
     *
     * @param  array<string, mixed>|self  $field
     */
    public static function fromArray(array|self $field): self {
        if ($field instanceof self) {
            return $field;
        }
        $type = FieldType::tryFrom((string) ($field['type'] ?? 'text'));
        if ($type === null) {
            throw new InvalidArgumentException(sprintf('Unbekannter Feldtyp "%s".', (string) ($field['type'] ?? '')));
        }

        return new self(
            key: (string) ($field['key'] ?? ''),
            label: (string) ($field['label'] ?? ''),
            type: $type,
            required: (bool) ($field['required'] ?? false),
            secret: array_key_exists('secret', $field) ? (bool) $field['secret'] : null,
            default: $field['default'] ?? null,
            options: (array) ($field['options'] ?? []),
            help: isset($field['help']) ? (string) $field['help'] : null,
        );
    }

    /**
     * Array-Form für Konsumenten, die das Legacy-Format erwarten (Blade-Partials).
     *
     * @return array{key: string, label: string, type: string, options?: array<string, string>, help?: string, required?: bool, default?: mixed, secret?: bool}
     */
    public function toArray(): array {
        $out = ['key' => $this->key, 'label' => $this->label, 'type' => $this->type->value];
        if ($this->options !== []) {
            $out['options'] = $this->options;
        }
        if ($this->help !== null) {
            $out['help'] = $this->help;
        }
        if ($this->required) {
            $out['required'] = true;
        }
        if ($this->default !== null) {
            $out['default'] = $this->default;
        }
        if ($this->secret !== null) {
            $out['secret'] = $this->secret;
        }

        return $out;
    }
}
