<?php

declare(strict_types=1);

namespace App\Tests\Story;

use App\Tests\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: self::NAME)]
final class AppStory extends Story
{
    public const string NAME = 'main';
    public const string ADMIN_EMAIL = 'staff@tacticmedia.com.au';

    public function build(): void
    {
        UserFactory::new()->admin()->create([
            'displayName' => 'Staff Member',
            'email' => self::ADMIN_EMAIL,
        ]);
    }
}
