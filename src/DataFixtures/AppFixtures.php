<?php
namespace App\DataFixtures;
use App\Entity\{Course, Sprint, Card, Task};
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void {
        // Safe to append repeatedly: never purge or duplicate our sample course.
        if ($manager->getRepository(Course::class)->findOneBy(['title' => 'PHP Backend Developer'])) { return; }
        $course = new Course();
        $course->title = 'PHP Backend Developer';
        $course->description = 'От основ PHP до собственного REST API. Изучайте язык, проектируйте понятный код и работайте с базами данных через практические задания.';
        $course->status = 'published';
        $manager->persist($course);
        $program = [
            ['Основы PHP 8', 'Освойте синтаксис, типы данных и повседневные инструменты PHP.', [
                ['Первые шаги в PHP', 'Настройте окружение и напишите первую программу.', 'in_progress', [
                    ['Синтаксис и типы данных', 'Прочитайте о переменных, типах данных и выражениях в PHP. Попробуйте примеры в своей среде.', 'reading', 'https://www.php.net/manual/ru/language.basic-syntax.php'],
                    ['Ваш первый CLI-скрипт', 'Создайте скрипт, который принимает имя и выводит приветствие. Обработайте отсутствие аргумента.', 'practice', null],
                    ['Строгие типы', 'Что меняет declare(strict_types=1)? Приведите пример вызова функции с неверным типом аргумента.', 'question', null],
                ]],
                ['Коллекции и функции', 'Научитесь преобразовывать данные и выделять повторяющуюся логику.', 'todo', [
                    ['Массивы в PHP', 'Изучите способы создания и обхода массивов, сравните ключи и значения.', 'reading', 'https://www.php.net/manual/ru/language.types.array.php'],
                    ['Фильтр каталога', 'Создайте массив товаров и функцию, которая отбирает товары по цене. Добавьте проверку пустого результата.', 'practice', null],
                ]],
                ['Обзор возможностей языка', 'Закрепите основные идеи современного PHP.', 'done', [
                    ['Видеоматериалы PHP', 'Выберите вводный доклад на официальном канале PHP и запишите три новых факта о языке.', 'video', 'https://www.youtube.com/@PHPFoundation'],
                ]],
            ]],
            ['ООП и SOLID', 'Создавайте классы с понятными обязанностями и явными зависимостями.', [
                ['Классы и интерфейсы', 'Разберите объекты, инкапсуляцию и контракты.', 'todo', [
                    ['Объектная модель PHP', 'Прочитайте о классах, свойствах, методах и интерфейсах.', 'reading', 'https://www.php.net/manual/ru/language.oop5.php'],
                    ['Каталог с репозиторием', 'Опишите интерфейс репозитория и его реализацию в памяти. Передайте зависимость через конструктор.', 'practice', null],
                ]],
                ['Одна ответственность', 'Потренируйтесь разделять работу с данными и их представление.', 'todo', [
                    ['Границы ответственности', 'Почему отправку уведомлений лучше вынести из сущности курса? Предложите разделение обязанностей.', 'question', null],
                ]],
            ]],
            ['Базы данных', 'Познакомьтесь с PostgreSQL, связями между таблицами и транзакциями.', [
                ['Основы PostgreSQL', 'Научитесь проектировать таблицы и выбирать данные.', 'todo', [
                    ['Введение в SQL', 'Пройдите официальный вводный учебник PostgreSQL.', 'reading', 'https://www.postgresql.org/docs/current/tutorial-sql.html'],
                    ['Схема учебной платформы', 'Спроектируйте таблицы курсов и спринтов. Добавьте внешний ключ и запрос с JOIN.', 'practice', null],
                ]],
                ['Работа с Doctrine', 'Перейдите от таблиц к объектной модели.', 'todo', [
                    ['Ассоциации Doctrine', 'Изучите owning side и ManyToOne. Объясните, на какой стороне хранится внешний ключ.', 'reading', 'https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/association-mapping.html'],
                ]],
            ]],
            ['REST API', 'Постройте понятный JSON API с корректными статусами и обработкой ошибок.', [
                ['HTTP и маршрутизация', 'Разберите запросы, ответы и структуру маршрутов Symfony.', 'todo', [
                    ['Контроллеры Symfony', 'Изучите обработку запросов и возврат JSON-ответов.', 'reading', 'https://symfony.com/doc/7.4/controller.html'],
                    ['API каталога', 'Создайте GET-методы списка и просмотра курса. Для отсутствующего курса верните JSON с HTTP 404.', 'practice', null],
                    ['HTTP-статусы', 'Чем отличаются 400, 404 и 422? Приведите пример для каждого ответа.', 'question', null],
                ]],
            ]],
        ];
        foreach ($program as $sprintIndex => [$title, $description, $cards]) {
            $sprint = new Sprint(); $sprint->course = $course; $sprint->title = $title; $sprint->description = $description; $sprint->position = $sprintIndex + 1; $manager->persist($sprint);
            foreach ($cards as $cardIndex => [$title, $description, $status, $tasks]) {
                $card = new Card(); $card->sprint = $sprint; $card->title = $title; $card->description = $description; $card->status = $status; $card->position = $cardIndex + 1; $manager->persist($card);
                foreach ($tasks as $taskIndex => [$title, $description, $type, $url]) {
                    $task = new Task(); $task->card = $card; $task->title = $title; $task->description = $description; $task->type = $type; $task->contentUrl = $url; $task->position = $taskIndex + 1; $manager->persist($task);
                }
            }
        }
        $manager->flush();
    }
}
