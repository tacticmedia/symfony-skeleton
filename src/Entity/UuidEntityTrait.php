<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * @phpstan-require-implements UuidEntityInterface
 */
trait UuidEntityTrait
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    public Uuid $id {
        get => $this->id ??= new UuidV7();
        set(Uuid $value) {
            if (isset($this->id) && !$this->id->equals($value)) {
                throw new \InvalidArgumentException(sprintf("Property %s can't be changed.", __PROPERTY__));
            }

            $this->id = $value;
        }
    }

    public function equals(?UuidEntityInterface $uuid): bool
    {
        return $this->id->equals($uuid?->id);
    }
}
