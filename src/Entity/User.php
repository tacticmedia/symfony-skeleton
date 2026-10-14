<?php

declare(strict_types=1);

namespace App\Entity;

use App\I18n\TranslatableNounInterface;
use App\Repository\UserRepository;
use DH\Auditor\Attribute\Auditable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\TranslatableMessage;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user_account')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\HasLifecycleCallbacks]
#[Auditable]
class User implements UuidEntityInterface, UserInterface, EquatableInterface, TranslatableNounInterface
{
    use UuidEntityTrait;

    public static function noun(int $count = 1): TranslatableMessage
    {
        return new TranslatableMessage('entity.user', ['count' => $count]);
    }

    /**
     * @var non-empty-lowercase-string
     */
    #[ORM\Column(type: Types::STRING, length: 255, unique: true, nullable: false)]
    public string $email {
        set => strtolower($value);
    }

    /**
     * @var list<'ROLE_USER'|'ROLE_ADMIN'>
     */
    #[ORM\Column(type: 'text[]', length: 255, nullable: true)]
    public ?array $roles = null {
        get => empty($this->roles) ? ['ROLE_USER'] : $this->roles;
        set {
            if (null !== $value) {
                $value = $value
                        |> array_unique(...)
                        |> array_values(...);
                sort($value);
                if ([] === $value || $value === ['ROLE_USER']) {
                    $value = null;
                }
            }

            $this->roles = $value;
        }
    }

    /**
     * @return list<'ROLE_USER'|'ROLE_ADMIN'>
     */
    public function getRoles(): array
    {
        // The get hook already substitutes ['ROLE_USER']; `??` narrows the type.
        return $this->roles ?? ['ROLE_USER'];
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * Returning false ends the live session. Implementing it replaces the
     * framework's own comparison, so the identifier and roles stay in it next to
     * the suspension and closure flags.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->getUserIdentifier() === $this->getUserIdentifier()
            && $user->active === $this->active
            && $user->closed === $this->closed
            && $user->getRoles() === $this->getRoles();
    }

    #[ORM\Column(type: Types::STRING, length: 40, nullable: false)]
    public string $loginLinkSignatureHash {
        set => sha1($value);
    }

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $displayName = null;

    /**
     * IANA identifier.
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    public ?string $timezone = null;

    /**
     * An admin suspension, independent of {@see $closedAt}.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    public bool $active = true;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $closedAt = null;

    public bool $closed {
        get => $this->closedAt instanceof \DateTimeImmutable;
    }

    public function __construct()
    {
        $this->loginLinkSignatureHash = random_bytes(64);
    }
}
