<?php
declare(strict_types=1);
namespace App\Coding\Form;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{ChoiceType, TextareaType, HiddenType};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
final class CodeSolutionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = []; foreach ($options['languages'] as $language) { $choices[strtoupper($language)] = $language; }
        $builder->add('language', ChoiceType::class, ['label' => 'Язык решения', 'choices' => $choices])
            ->add('sourceCode', TextareaType::class, ['label' => 'Код решения', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 65536)],
                'attr' => ['spellcheck' => 'false', 'autocapitalize' => 'off', 'autocomplete' => 'off', 'class' => 'code-input', 'rows' => 20]])
            ->add('versionId', HiddenType::class);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setRequired('languages'); $resolver->setAllowedTypes('languages', 'array'); }
}
