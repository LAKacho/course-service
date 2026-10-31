<?php
namespace App\Form;

use App\Entity\PathCourse;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PathCoursePositionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('position', IntegerType::class, ['label' => 'Порядок', 'empty_data' => '0', 'attr' => ['min' => 0, 'max' => 2147483647]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PathCourse::class]);
    }
}
