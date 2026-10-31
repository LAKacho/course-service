# Course Service

Учебная платформа на Symfony 7.4, PHP 8.4, PostgreSQL 16, Doctrine ORM, Twig, Bootstrap 5 и vanilla JavaScript.

Платформа объединяет курсы, персональные учебные маршруты, входную диагностику и практические задания с редактором PHP/Go. Управление доступом построено на разрешениях PBAC, без фиксированных ролей.

## Что есть

- Направления обучения и упорядоченные курсы. Один курс может входить в несколько направлений.
- Курсы → спринты → карточки → задания, административный канбан.
- Назначения программ ученикам, зависимости заданий, проверка циклов, открытие следующего квеста после обязательного предыдущего.
- «Мой путь»: текущий квест, карта известных глав, журнал и прогресс доступной части программы.
- Импорт программ из JSON: dry-run, внешние ID, повторный импорт без дублей, транзакции и сохранение прогресса.
- Диагностика PHP Backend, Go Backend и Full Backend: 48 оригинальных вопросов, 17 компетенций, снимки попыток, детерминированная предварительная оценка, рекомендации тем.
- Практика PHP/Go: редактор с подсветкой, версии заданий, сохранение решений и история.
- Разрешения на действия и конкретные курсы/направления, управление назначениями прав, отдельный доступ к статистике.
- AJAX-формы и навигация, серверная валидация, CSRF, адаптивный интерфейс. Формы работают и без JavaScript.
- Вход по имени существующей учётной записи и выход из сессии.

**Вход без пароля предназначен только для локальной демонстрации.** Он не подтверждает личность: знающий логин может выбрать соответствующую учётную запись. Compose публикует приложение только на 127.0.0.1. Для общего доступа необходима настоящая аутентификация. Репозиторий открыт, но это не означает готовность приложения к публичному развёртыванию.

**Code Runner не подключён.** Кнопки «Проверить» и «Сдать» сохраняют код, но честно сообщают о недоступности проверки и не дают зачёт. Пользовательский PHP/Go не исполняется в Symfony, Apache или контейнере приложения. Запуск кода требует отдельного изолированного сервиса. Внешние Authorization, Notification и Diagnostic Service не подключены без реальных контрактов.

## Быстрый запуск через Docker

Требуется Docker с Compose и работающим Linux engine. Локальные PHP, Composer, PostgreSQL и XAMPP для запуска не нужны.

```powershell
git clone https://github.com/LAKacho/course-service.git
cd course-service

# Создать .env.docker со случайными секретами; файл не публикуется.
docker run --rm -v "${PWD}:/workspace" -w /workspace php:8.4-cli php tools/configure-docker.php

docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker exec --user www-data app php bin/console doctrine:migrations:migrate --no-interaction
docker compose --env-file .env.docker exec --user www-data app php bin/console app:demo:install
```

Открыть [http://localhost:8080](http://localhost:8080). При первом запуске образы скачиваются и собираются. Состояние: `docker compose --env-file .env.docker ps`. PostgreSQL ожидается по healthcheck до старта приложения; готовность контейнера сама по себе не заменяет применение миграций. [Документация Docker Compose](https://docs.docker.com/compose/how-tos/startup-order/).

В Linux первый шаг генерации файла можно выполнить от своего UID/GID:

```bash
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD:/workspace" -w /workspace php:8.4-cli php tools/configure-docker.php
```

Если PHP уже установлен, вместо временного контейнера достаточно `php tools/configure-docker.php`. Повторная генерация не перезаписывает существующий файл. Для другого порта измените APP_PORT в .env.docker.

## Демонстрационные логины

После **явного** выполнения app:demo:install доступны:

| Логин | Возможности |
| --- | --- |
| demo-student | Назначенный Go-курс, диагностика, редактор и сохранение решений |
| go-editor | Управление материалами Go, без PHP и статистики; назначение обучения не возникает автоматически |
| stats-reader | Статистика без доступа к чужим курсам |
| local-access-manager | Управление материалами и разрешениями других пользователей |

Эти имена — только наборы демонстрационных назначений, не роли. Паролей нет. Чтобы сменить пользователя, нажмите «Выйти» и введите другой логин. Вход не создаёт аккаунт и не назначает права.

Команда заполнения добавляет небольшой пример Go, вводный PHP-курс, банк диагностики и кодовую практику. Она не очищает таблицы; повтор не создаёт дубликатов, но явным запуском повторно назначает демонстрационные разрешения. Не используйте её для восстановления своей базы: для этого нужен резервный файл.

## Основные страницы

| URL | Назначение |
| --- | --- |
| /login | Вход по имени |
| /learn | Персональный учебный путь |
| /courses, /learning-paths | Курсы и направления в разрешённой области |
| /mentor/learning | Назначения программ и зависимости заданий |
| /mentor/programs | Полная структура разрешённых материалов |
| /assessments | Входная диагностика |
| /learn/coding/{taskId} | Практическое задание |
| /manage/access | Назначение и отзыв разрешений |
| / | Обзор и статистика, только с statistics.view |

Интерфейс создания заданий находится в карточке курса. Для coding-задания нажмите «Настроить практику кода»: каждое сохранение создаёт новую версию с публичными/скрытыми тестами.

## Пользователи и разрешения

Создать локальную учётную запись без прав:

```powershell
docker compose --env-file .env.docker exec --user www-data app php bin/console app:user:create alex --name="Александр"
```

Затем войти как local-access-manager и выдать alex нужные действия и область в /manage/access. Для учёбы дополнительно назначить курс в /mentor/learning. Например, course.view + task.view + task.submit на Go-курс дают чтение/сдачу только Go при наличии Enrollment и открытого квеста.

Каталог: course.view/create/edit/delete, task.view/submit/review, assessment.take, assessment.results.view, statistics.view, access.manage. course.delete зарезервировано, самого удаления курса пока нет. Для диагностики, статистики и управления правами допустима только global-область. Другие разрешения поддерживают global, learning_path и course. Направление распространяет право только на непосредственно входящие курсы, без перехода через общий курс на другое направление.

Самовыдача/отзыв прав и выдача access.manage через веб запрещены. Оператор не может делегировать то, чего нет в собственной области. При необходимости доверенный локальный оператор может явно подготовить начальные права CLI-командой:

```powershell
docker compose --env-file .env.docker exec --user www-data app php bin/console app:access:bootstrap operator --manage
docker compose --env-file .env.docker exec --user www-data app php bin/console app:user:create operator
```

Не вызывайте bootstrap автоматически при регистрации. В рабочем приложении имя выбирается сессией, а не query-параметром userId. Правила выполняются для HTML, AJAX и JSON. Чужие попытки/исходники и скрытые задания не выдаются ученическому интерфейсу. Нет прав на объект — 404; нет глобального действия — 403; нет входа в API — 401.

## JSON API

Read-only API использует ту же сессию и PBAC:

- /api/courses и вложенные endpoints спринтов, карточек, заданий и прогресса;
- /api/learning-paths, /api/learning-paths/{id}, /api/learning-paths/{id}/courses;
- /api/me/learning-paths, /api/me/quests, /api/me/progress;
- /api/me/assessments, /api/me/assessment-attempts/{id}, /api/me/assessment-attempts/{id}/result.

Изменения выполняются HTML-формами с CSRF. Клиент не задаёт итоговую оценку, passed или текущую личность.

## Импорт программы

```powershell
docker compose --env-file .env.docker exec --user www-data app php bin/console app:learning examples/go-backend-developer.json --dry-run
docker compose --env-file .env.docker exec --user www-data app php bin/console app:learning examples/go-backend-developer.json
```

Примеры находятся в examples/. Элементы сопоставляются по source/externalId. Импорт проверяется до записи и сохраняется транзакционно, повтор не дублирует элементы и не сбрасывает существующее выполнение. Это доверенная консольная операция.

## Проверки

```powershell
docker compose --env-file .env.docker --profile tools run --rm --build tests
docker compose --env-file .env.docker exec --user www-data app php bin/console doctrine:schema:validate
docker compose --env-file .env.docker exec --user www-data app php bin/console doctrine:migrations:status
docker compose --env-file .env.docker exec --user www-data app php bin/console lint:container
```

PHPUnit использует отдельную SQLite внутри временного контейнера, не рабочую PostgreSQL-базу. Проверяются обучение/прогресс, импорт, диагностика, кодовые решения и переходы, PBAC, CSRF, отсутствие утечек, вход/выход и блокировка учётной записи. MockCodeRunnerClient находится только в tests/Support и не включён в пользовательскую проверку.

## Данные, остановка и обновление

PostgreSQL хранится в именованном volume postgres_data; кеш, журнал и сессии — в application_var. База не публикует порт на хост. Приложение подключается от отдельного course_app без superuser/создания ролей или баз. Учебный контейнер приложения не является песочницей для пользовательского кода.

Остановка с сохранением данных:

```powershell
docker compose --env-file .env.docker down
```

Не удаляйте volumes, если нужны данные. После обновления исходников пересоберите образ, примените новые миграции и очистите кеш:

```powershell
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker exec --user www-data app php bin/console doctrine:migrations:migrate --no-interaction
docker compose --env-file .env.docker exec --user www-data app php bin/console cache:clear
```

Рабочие данные, резервные копии, .env.docker и .env.local не входят в Git. Журналы контейнеров: `docker compose --env-file .env.docker logs --tail=100 app db`; журнал приложения находится в volume по пути var/log/prod.log.

Резервная копия без двоичного перенаправления PowerShell:

```powershell
docker compose --env-file .env.docker exec db pg_dump -U course_app -d course_service -Fc --no-owner --no-acl -f /tmp/course-service.dump
docker compose --env-file .env.docker cp db:/tmp/course-service.dump ./course-service.dump
```

Храните файл приватно. Восстановление выполняется pg_restore в новую пустую базу; не применяйте --clean к рабочим данным. Не загружайте дампы в GitHub.

## Устройство проекта

```text
src/Entity, Repository, Service, Controller, Form  — Course Service
src/Access                                      — PBAC и области
src/Auth                                        — локальный вход и подготовка аккаунтов
src/Assessment                                  — диагностика и исторические результаты
src/Coding                                      — версии практики, решения и граница runner
src/Integration                                 — неподключённые внешние порты
templates/, public/assets/                      — Twig, стили, AJAX и редактор
migrations/                                     — добавочные PostgreSQL-миграции
examples/, resources/                           — авторские демопрограммы и банк
tests/                                          — PHPUnit и тестовый runner
docker/, compose.yaml, Dockerfile               — окружение
```

Course Service владеет материалами, назначениями и TaskProgress. Диагностика сохраняет собственные снимки/результаты и не меняет назначения автоматически. Code Runner отвечает за изолированное исполнение; адаптер пока сообщает недоступность. Для подключения настоящих внешних сервисов нужны их реальные спецификации.

Оценка диагностики предварительная, детерминированная; Senior тестом не подтверждается. Демобанк и примеры открыты в исходниках и не предназначены для защищённого экзамена. Исходники/ожидаемые значения реальных закрытых тестов должны оставаться приватными на стороне соответствующих сервисов.
