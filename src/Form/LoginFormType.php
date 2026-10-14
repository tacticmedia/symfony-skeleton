<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\LoginFormDto;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;

/**
 * @extends AbstractType<LoginFormDto>
 */
class LoginFormType extends AbstractType
{
    public function __construct(
        #[Autowire(service: 'stimulus.helper')]
        private readonly StimulusHelper $stimulus,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('timezone', HiddenType::class, [
                // Populated by the login Stimulus controller (assets/controllers/login_controller.js)
                'attr' => $this->targetAttr('timezone'),
            ])
            ->add('email', EmailType::class, [
                'label' => new TranslatableMessage('form.login.email.label'),
                'attr' => [
                    'autofocus' => 'true',
                    'placeholder' => new TranslatableMessage('form.login.email.placeholder'),
                    'autocomplete' => 'none',
                    ...$this->targetAttr('email'),
                ],
                'row_attr' => [
                    'class' => 'mb-1',
                ],
            ])
        ;

        $builder->add('remember', CheckboxType::class, [
            'label' => new TranslatableMessage('form.login.remember.label'),
            'required' => false,
            'mapped' => false,
            'attr' => $this->targetAttr('remember'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $controller = $this->stimulus->createStimulusAttributes();
        $controller->addController('login');

        $resolver->setDefaults([
            'data_class' => LoginFormDto::class,
            'attr' => $controller->toArray(),
        ]);
    }

    /**
     * @return array<string, string|true>
     */
    private function targetAttr(string $target): array
    {
        $attributes = $this->stimulus->createStimulusAttributes();
        $attributes->addTarget('login', $target);

        return $attributes->toArray();
    }
}
