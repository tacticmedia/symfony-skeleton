<?php

declare(strict_types=1);

namespace App\Dto;

use App\Form\LoginFormType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see LoginFormType
 */
class LoginFormDto
{
    #[Assert\Timezone]
    public ?string $timezone = null;

    /**
     * Normalized on set like {@see User::$email}, so rate limiting, lookup and
     * auto-provisioning all see the stored form.
     */
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null {
        set => null === $value ? null : strtolower(trim($value));
    }
}
