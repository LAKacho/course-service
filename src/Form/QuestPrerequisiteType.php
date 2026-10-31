<?php
namespace App\Form;

use App\Entity\{Task, QuestPrerequisite};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuestPrerequisiteType extends AbstractType
{
    public function __construct(private \App\Access\ScopedChoices $choices) {}
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = ['class' => Task::class, 'choices' => $this->choices->editable(Task::class), 'choice_label' => static fn(Task $task): string => $task->title.' · №'.$task->id, 'placeholder' => 'Выберите задание'];
        $builder->add('task', EntityType::class, $choices + ['label' => 'Открываемое задание'])
            ->add('requiredTask', EntityType::class, $choices + ['label' => 'Обязательное предыдущее задание']);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => QuestPrerequisite::class]); }
}
