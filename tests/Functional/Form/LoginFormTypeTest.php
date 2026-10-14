<?php

declare(strict_types=1);

namespace App\Tests\Functional\Form;

use App\Dto\LoginFormDto;
use App\Form\LoginFormType;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class LoginFormTypeTest extends KernelTestCase
{
    #[TestDox('The login form is the element of the login Stimulus controller')]
    public function testFormIsWiredToTheStimulusController(): void
    {
        self::bootKernel();

        $factory = self::getContainer()->get(FormFactoryInterface::class);
        // CSRF is disabled here so the view can be built without an active request/session;
        // it stays enabled in production via configureOptions().
        $view = $factory
            ->create(LoginFormType::class, new LoginFormDto(), ['csrf_protection' => false])
            ->createView();

        self::assertSame('login', $view->vars['attr']['data-controller'] ?? null);
    }
}
