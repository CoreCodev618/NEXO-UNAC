<?php

declare(strict_types=1);

use App\Models\UserModel;
use CodeIgniter\Shield\Entities\User;

/**
 * Usuario de prueba con email institucional en auth_identities.
 * fake(UserModel) no crea la identidad email; el API la lee de ahí.
 */
function testUserWithEmail(array $overrides = [], string $email = 'ana@unac.edu.pe'): User
{
    $user = fake(UserModel::class, $overrides);
    $user->createEmailIdentity(['email' => $email, 'password' => 'Password123!']);

    return $user;
}
