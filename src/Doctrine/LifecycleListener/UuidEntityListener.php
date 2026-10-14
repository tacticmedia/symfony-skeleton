<?php

declare(strict_types=1);

namespace App\Doctrine\LifecycleListener;

use App\Entity\UuidEntityInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Component\Uid\Uuid;

/**
 * Reads the id so the lazy-init hook in {@see \App\Entity\UuidEntityTrait} sets it
 * before the insert. The ORM reads the backing value by raw reflection and
 * bypasses the hook. Without this listener, every persist fails.
 */
#[AsDoctrineListener(event: 'prePersist')]
class UuidEntityListener
{
    public function prePersist(PrePersistEventArgs $args): ?Uuid
    {
        $entity = $args->getObject();

        if (!$entity instanceof UuidEntityInterface) {
            return null;
        }

        return $entity->id;
    }
}
