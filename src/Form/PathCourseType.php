<?php
namespace App\Form;

use App\Entity\{Course, PathCourse};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PathCourseType extends AbstractType
{
    public function __construct(private \App\Access\ScopedChoices $choices) {}
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('course', EntityType::class, [
                'class' => Course::class, 'choices' => $this->choices->editable(Course::class),
                'choice_label' => static fn(Course $course): string => $course->title.' · №'.$course->id,
                'label' => 'Существующий курс',
                'placeholder' => 'Выберите курс',
            ])
            ->add('position', IntegerType::class, ['label' => 'Порядок', 'empty_data' => '0', 'attr' => ['min' => 0, 'max' => 2147483647]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PathCourse::class]);
    }
}
