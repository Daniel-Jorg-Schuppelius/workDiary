<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveProjectTimeTargetsRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Enums\User\Permission;
use App\Http\Requests\BaseFormRequest;
use App\Models\Platform\User;
use App\Support\SqidEncoder;
use CommonToolkit\Helper\Data\DateHelper;
use CommonToolkit\ValueObjects\Duration;
use Illuminate\Validation\Validator;

/**
 * Sollzeiten eines Projekts (Feature 194): Projektvorgabe und Personenzeilen je
 * Wochentag als HH:MM (leer = keine Vorgabe bzw. erbt), Ausnahmen je Datum,
 * eine Gültigkeit. Personen kommen als Sqid und müssen zuweisbar sein.
 */
class SaveProjectTimeTargetsRequest extends BaseFormRequest {
    private const string CLOCK = '/^(?:[0-9]|1[0-9]|2[0-4]):[0-5][0-9]$/';

    public function authorize(): bool {
        return $this->user()?->can(Permission::ProjectTargetsManage->value) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'default' => ['nullable', 'array'],
            'default.*' => ['nullable', 'string', 'regex:' . self::CLOCK],
            'members' => ['nullable', 'array'],
            'members.*' => ['array'],
            'members.*.*' => ['nullable', 'string', 'regex:' . self::CLOCK],
            'exceptions' => ['nullable', 'array', 'max:500'],
            'exceptions.*.user' => ['nullable', 'string'],
            'exceptions.*.date' => ['required_with:exceptions.*.minutes', 'nullable', 'date_format:Y-m-d'],
            'exceptions.*.minutes' => ['required_with:exceptions.*.date', 'nullable', 'string', 'regex:' . self::CLOCK],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $v): void {
            foreach (array_keys((array) $this->input('default', [])) as $weekday) {
                if (! is_numeric($weekday) || (int) $weekday < 1 || (int) $weekday > 7) {
                    $v->errors()->add('default', (string) __('Ungültiger Wochentag.'));
                }
            }
            foreach ((array) $this->input('members', []) as $sqid => $week) {
                if ($this->decodeUser((string) $sqid) === null) {
                    $v->errors()->add('members', (string) __('Unbekannte Person in den Sollzeiten.'));
                }
                foreach (array_keys((array) $week) as $weekday) {
                    if (! is_numeric($weekday) || (int) $weekday < 1 || (int) $weekday > 7) {
                        $v->errors()->add('members', (string) __('Ungültiger Wochentag.'));
                    }
                }
            }
            foreach ((array) $this->input('exceptions', []) as $index => $exception) {
                $user = (string) ($exception['user'] ?? '');
                if ($user !== '' && $this->decodeUser($user) === null) {
                    $v->errors()->add("exceptions.$index.user", (string) __('Unbekannte Person in den Sollzeiten.'));
                }
                $date = (string) ($exception['date'] ?? '');
                if ($date !== '' && ! DateHelper::isDateTime($date, 'Y-m-d')) {
                    $v->errors()->add("exceptions.$index.date", (string) __('Ungültiges Datum.'));
                }
            }
        });
    }

    /** @return array<int, int|null> Wochentag => Minuten */
    public function defaultMinutes(): array {
        $out = [];
        foreach ((array) $this->input('default', []) as $weekday => $clock) {
            $out[(int) $weekday] = self::minutes($clock);
        }

        return $out;
    }

    /** @return array<int, array<int, int|null>> user_id => [Wochentag => Minuten] */
    public function memberMinutes(): array {
        $out = [];
        foreach ((array) $this->input('members', []) as $sqid => $week) {
            $userId = $this->decodeUser((string) $sqid);
            if ($userId === null) {
                continue;
            }
            foreach ((array) $week as $weekday => $clock) {
                $out[$userId][(int) $weekday] = self::minutes($clock);
            }
        }

        return $out;
    }

    /** @return list<array{user_id: int|null, date: string, minutes: int}> */
    public function exceptionRows(): array {
        $out = [];
        foreach ((array) $this->input('exceptions', []) as $exception) {
            $date = (string) ($exception['date'] ?? '');
            $minutes = self::minutes($exception['minutes'] ?? null);
            if ($date === '' || $minutes === null) {
                continue;
            }
            $user = (string) ($exception['user'] ?? '');
            $out[] = ['user_id' => $user === '' ? null : $this->decodeUser($user), 'date' => $date, 'minutes' => $minutes];
        }

        return $out;
    }

    private static function minutes(mixed $clock): ?int {
        if (! is_string($clock) || trim($clock) === '') {
            return null;
        }

        return Duration::fromClock(trim($clock))->getTotalMinutes();
    }

    private function decodeUser(string $sqid): ?int {
        $id = ctype_digit($sqid) ? (int) $sqid : app(SqidEncoder::class)->decode(User::class, $sqid);

        return $id !== null && $id > 0 ? $id : null;
    }
}
