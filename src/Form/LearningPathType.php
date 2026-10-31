<?php
namespace App\Form;

use App\Entity\LearningPath;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{TextType, TextareaType, ChoiceType};
use Symfony\Component\OptionsResolver\OptionsResolver;

class LearningPathType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Название', 'empty_data' => '', 'attr' => ['maxlength' => 180]])
            ->add('slug', TextType::class, ['label' => 'Адрес (slug)', 'empty_data' => '', 'help' => 'Например: go-backend-developer. Адрес должен быть уникальным.', 'attr' => ['maxlength' => 180]])
            ->add('description', TextareaType::class, ['label' => 'Описание', 'required' => false, 'empty_data' => '', 'attr' => ['rows' => 5]])
            ->add('status', ChoiceType::class, ['label' => 'Статус', 'choices' => LearningPath::STATUSES]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LearningPath::class]);
    }
}
