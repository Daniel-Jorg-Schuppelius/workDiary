<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncFormRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Platform\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;

/**
 * Prüft die Nutzlast eines Offline-Befehls mit dem FormRequest des
 * Online-Formulars (MVP-1593–1595): Vorverarbeitung (Sqids, Zeitzone), Regeln
 * und Feldnamen sind damit dieselben — keine zweite Regelliste im Handler.
 * Wirft wie online eine ValidationException.
 */
final class SyncFormRequest {
    /**
     * @template T of FormRequest
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $payload
     * @return T
     */
    public static function validate(string $class, array $payload, User $user): FormRequest {
        $request = $class::create('/', 'POST', $payload);
        $request->setContainer(app())->setRedirector(app(Redirector::class));
        $request->setUserResolver(static fn (): User => $user);
        $request->validateResolved();

        return $request;
    }
}
