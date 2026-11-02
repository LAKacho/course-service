<?php
declare(strict_types=1);
namespace App\Coding\Infrastructure\Form;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{TextType, TextareaType};
use Symfony\Component\Validator\Constraints as Assert;
final class ExerciseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, ['label' => 'Название', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 180)]])
            ->add('statement', TextareaType::class, ['label' => 'Условие и формат входа/выхода', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 20000)]])
            ->add('php', TextareaType::class, ['label' => 'Стартовый PHP-код (пусто — язык не разрешён)', 'required' => false])
            ->add('go', TextareaType::class, ['label' => 'Стартовый Go-код (пусто — язык не разрешён)', 'required' => false])
            ->add('publicTests', TextareaType::class, ['label' => 'Публичные тесты JSON: массив id/input/expected'])
            ->add('hiddenTests', TextareaType::class, ['label' => 'Скрытые тесты JSON (только ментор)']);
    }
}
