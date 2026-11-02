<?php
namespace App\Learning\Infrastructure\Form;
use App\Learning\Domain\Entity\LearningItem;
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\Card;
use App\Learning\Domain\Entity\Task;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\{TextType, TextareaType, ChoiceType, IntegerType, UrlType};
use Symfony\Component\OptionsResolver\OptionsResolver;
class LearningType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $item = $options['data'];
        $builder->add('title', TextType::class, ['label' => 'Название', 'empty_data' => '', 'attr' => ['maxlength' => 180]])
            ->add('description', TextareaType::class, ['label' => 'Описание', 'required' => false, 'empty_data' => '', 'attr' => ['rows' => 5]]);
        if ($item instanceof Course || $item instanceof Card) { $builder->add('status', ChoiceType::class, ['label' => 'Статус', 'choices' => $item::STATUSES]); }
        if (!$item instanceof Course) { $builder->add('position', IntegerType::class, ['label' => 'Порядок', 'attr' => ['min' => 0], 'help' => 'При одинаковом порядке используется дата добавления.']); }
        if ($item instanceof Task) {
            $builder->add('type', ChoiceType::class, ['label' => 'Тип задания', 'choices' => Task::TYPES, 'disabled' => $item->type === 'coding'])
                ->add('contentUrl', UrlType::class, ['label' => 'Ссылка на материал', 'required' => false, 'default_protocol' => null, 'help' => 'Полная ссылка с http:// или https://']);
        }
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => LearningItem::class]); }
}
