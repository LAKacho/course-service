<?php
namespace App\Form;

use App\Entity\{Enrollment, LearningPath, Course};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EnrollmentType extends AbstractType
{
    public function __construct(private \App\Access\ScopedChoices $choices) {}
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('userId', TextType::class, ['label' => 'ID ученика', 'empty_data' => '', 'help' => 'Текущий демо-ученик: demo-student. Это поле управления ментора, не авторизация.'])
            ->add('learningPath', EntityType::class, ['class' => LearningPath::class, 'choices' => $this->choices->editable(LearningPath::class), 'choice_label' => 'title', 'label' => 'Направление', 'required' => false, 'placeholder' => 'Не выбрано'])
            ->add('course', EntityType::class, ['class' => Course::class, 'choices' => $this->choices->editable(Course::class), 'choice_label' => 'title', 'label' => 'Курс', 'required' => false, 'placeholder' => 'Не выбран']);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => Enrollment::class]); }
}
