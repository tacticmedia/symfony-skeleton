<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return User::class;
    }

    #[\Override]
    protected function defaults(): array
    {
        return [
            'active' => true,
            'displayName' => self::faker()->name(),
            'email' => self::faker()->unique()->safeEmail(),
            'loginLinkSignatureHash' => self::faker()->sha1(),
        ];
    }

    public function admin(): static
    {
        return $this->with(['roles' => ['ROLE_ADMIN']]);
    }
}
