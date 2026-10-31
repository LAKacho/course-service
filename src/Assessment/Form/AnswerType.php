<?php
namespace App\Assessment\Form;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AnswerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $question = $options['question'];
        $labels = array_column($question['options'], 'text', 'code');
        $multiple = $question['type'] === 'multiple_choice';
        $builder->add('selection', ChoiceType::class, ['label' => $multiple ? 'Выберите все подходящие варианты' : 'Выберите один вариант',
            'choices' => array_combine(array_keys($labels), array_keys($labels)), 'choice_label' => static fn(string $code): string => $labels[$code],
            'expanded' => true, 'multiple' => $multiple, 'constraints' => $multiple ? [new Assert\Count(min: 1)] : [new Assert\NotBlank()]]);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setRequired('question'); $resolver->setAllowedTypes('question', 'array'); }
}
