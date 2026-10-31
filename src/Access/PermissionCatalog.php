<?php
declare(strict_types=1);
namespace App\Access;
final class PermissionCatalog
{
    public const ALL = ['course.view'=>'Просмотр курсов','course.create'=>'Создание курсов и направлений','course.edit'=>'Управление материалами',
        'course.delete'=>'Удаление курса (операция пока отсутствует)','task.view'=>'Просмотр заданий','task.submit'=>'Выполнение заданий',
        'task.review'=>'Просмотр полной структуры заданий','assessment.take'=>'Прохождение диагностики',
        'assessment.results.view'=>'Просмотр своего результата диагностики','statistics.view'=>'Просмотр статистики','access.manage'=>'Управление разрешениями'];
    public const SCOPED = ['course.view','course.edit','course.delete','task.view','task.submit','task.review'];
    public static function validScope(string $permission, string $scope): bool
    {
        return isset(self::ALL[$permission]) && in_array($scope, ['global','course','learning_path'], true)
            && ($scope === 'global' || in_array($permission, self::SCOPED, true));
    }
}
