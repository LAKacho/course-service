<?php
namespace App\Mentor\Infrastructure\Form;

use App\Mentor\Domain\Entity\Enrollment;

use App\Learning\Domain\Entity\LearningPath;

use App\Learning\Domain\Entity\Course;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EnrollmentType extends AbstractType
{
    public function __construct(private \App\Access\Infrastructure\Form\ScopedChoices $choices) {}
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('userId', TextType::class, ['label' => 'ID ученика', 'empty_data' => '', 'help' => 'Например, 005. Сначала создайте учётную запись через app:user:create.'])
            ->add('course', EntityType::class, ['class' => Course::class, 'choices' => $this->choices->editable(Course::class), 'choice_label' => 'title', 'label' => 'Выберите курс для назначения', 'required' => false, 'placeholder' => 'Выберите курс'])
            ->add('learningPath', EntityType::class, ['class' => LearningPath::class, 'choices' => $this->choices->editable(LearningPath::class), 'choice_label' => 'title', 'label' => 'Или назначьте целое направление', 'required' => false, 'placeholder' => 'Не назначать направление']);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => Enrollment::class]); }
}
