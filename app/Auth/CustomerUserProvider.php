<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerUserProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * Provider fuer den `customer`-Guard. Filtert die Eloquent-Queries auf
 * users.customer_id IS NOT NULL, sodass nur Portal-Accounts ueber diesen
 * Guard authentifiziert werden koennen. Klassische interne User koennen
 * sich technisch nicht ueber das Portal anmelden.
 *
 * Passwoerter werden ausschliesslich per bcrypt geprueft (Cast `hashed`);
 * der Legacy-Klartextpfad ist hier bewusst nicht implementiert.
 */
class CustomerUserProvider extends EloquentUserProvider {
    public function __construct(Hasher $hasher) {
        parent::__construct($hasher, User::class);
    }

    public function retrieveById($identifier): ?Authenticatable {
        $user = parent::retrieveById($identifier);

        return $user instanceof User && $user->customer_id !== null ? $user : null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable {
        $user = parent::retrieveByToken($identifier, $token);

        return $user instanceof User && $user->customer_id !== null ? $user : null;
    }

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable {
        $email = $credentials['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return null;
        }

        return User::query()
            ->where('email', $email)
            ->whereNotNull('customer_id')
            ->first();
    }
}
