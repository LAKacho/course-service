<?php
namespace App\Service\Import;

use App\Entity\{Course, Card, Task, LearningPath};

class ProgramValidator
{
    /** Validate the complete document before looking up or modifying any entities. */
    public function validate(mixed $document): array
    {
        $errors = [];
        $nodes = [];
        $seen = [];
        if (!$document instanceof \stdClass) {
            throw new ImportValidationException(['$: ожидается объект программы.']);
        }
        $this->keys($document, ['version', 'source', 'learningPath'], '$', $errors);
        if (($document->version ?? null) !== 1) {
            $errors[] = '$.version: поддерживается только целое число 1.';
        }
        $source = $document->source ?? null;
        if (!is_string($source) || !preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/D', $source)) {
            $errors[] = '$.source: ожидается идентификатор источника, 1–80 символов a-z, 0-9, точки, дефисы, подчёркивания.';
        }
        $walk = function (mixed $item, string $kind, ?string $parentKey, string $location) use (&$walk, &$errors, &$nodes, &$seen, $source): void {
            if (!$item instanceof \stdClass) {
                $errors[] = $location.': ожидается объект '.$kind.'.';
                return;
            }
            $children = ['path' => 'courses', 'course' => 'sprints', 'sprint' => 'cards', 'card' => 'tasks'];
            $next = ['path' => 'course', 'course' => 'sprint', 'sprint' => 'card', 'card' => 'task'];
            $allowed = ['externalId', 'title', 'description'];
            if ($kind !== 'path') { $allowed[] = 'position'; }
            if (in_array($kind, ['path', 'course', 'card'], true)) { $allowed[] = 'status'; }
            if ($kind === 'path') { $allowed[] = 'slug'; }
            if ($kind === 'task') { array_push($allowed, 'type', 'contentUrl'); }
            if (isset($children[$kind])) { $allowed[] = $children[$kind]; }
            $this->keys($item, $allowed, $location, $errors);
            $externalId = $item->externalId ?? null;
            if (!is_string($externalId) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,119}$/D', $externalId)) {
                $errors[] = $location.'.externalId: ожидается устойчивый ID из 1–120 латинских букв, цифр, точек, дефисов или подчёркиваний.';
            } elseif (isset($seen[$externalId])) {
                $errors[] = $location.'.externalId: дублирующийся ID '.$externalId.'; первое вхождение: '.$seen[$externalId].'.';
            } else {
                $seen[$externalId] = $location;
            }
            $key = is_string($source) && is_string($externalId) ? $source.':'.$externalId : $location;
            $fields = [];
            foreach (['title' => 180, 'description' => 20000] as $field => $maximum) {
                $value = $item->{$field} ?? null;
                if (!is_string($value) || mb_strlen($value) > $maximum || ($field === 'title' && trim($value) === '')) {
                    $errors[] = $location.'.'.$field.': ожидается строка до '.$maximum.' символов'.($field === 'title' ? ', не пустая' : '').'.';
                } else {
                    $fields[$field] = $value;
                }
            }
            if ($kind !== 'path' && (!is_int($item->position ?? null) || $item->position < 0 || $item->position > 2147483647)) {
                $errors[] = $location.'.position: ожидается целое число от 0 до 2147483647.';
            }
            if ($kind === 'sprint' || $kind === 'card' || $kind === 'task') {
                $fields['position'] = $item->position ?? 0;
            }
            if (in_array($kind, ['path', 'course', 'card'], true)) {
                $choices = match ($kind) { 'path' => LearningPath::STATUSES, 'course' => Course::STATUSES, 'card' => Card::STATUSES };
                if (!is_string($item->status ?? null) || !in_array($item->status, $choices, true)) {
                    $errors[] = $location.'.status: допустимо '.implode(', ', array_values($choices)).'.';
                }
                $fields['status'] = $item->status ?? '';
            }
            if ($kind === 'path') {
                if (!is_string($item->slug ?? null) || strlen($item->slug) > 180 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $item->slug)) {
                    $errors[] = $location.'.slug: ожидаются строчные латинские буквы, цифры и дефисы, до 180 символов.';
                }
                $fields['slug'] = $item->slug ?? '';
            }
            if ($kind === 'task') {
                if (!in_array($item->type ?? null, Task::TYPES, true)) {
                    $errors[] = $location.'.type: допустимо reading, video, practice, question.';
                }
                $url = $item->contentUrl ?? null;
                if ($url !== null && (!is_string($url) || strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true))) {
                    $errors[] = $location.'.contentUrl: ожидается null или полный HTTP/HTTPS URL до 2048 символов.';
                }
                $fields['type'] = $item->type ?? '';
                $fields['contentUrl'] = $url;
            }
            $nodes[] = ['kind' => $kind, 'key' => $key, 'externalId' => $externalId, 'parentKey' => $parentKey, 'fields' => $fields, 'position' => $item->position ?? 0, 'location' => $location];
            if (isset($children[$kind])) {
                $childField = $children[$kind];
                if (!is_array($item->{$childField} ?? null)) {
                    $errors[] = $location.'.'.$childField.': ожидается JSON-массив (в том числе пустой []).';
                } else {
                    foreach ($item->{$childField} as $index => $child) {
                        $walk($child, $next[$kind], $key, $location.'.'.$childField.'['.$index.']');
                    }
                }
            }
        };
        $walk($document->learningPath ?? null, 'path', null, '$.learningPath');
        if ($errors) { throw new ImportValidationException($errors); }
        return ['source' => $source, 'nodes' => $nodes];
    }

    private function keys(\stdClass $object, array $allowed, string $location, array &$errors): void
    {
        foreach ($allowed as $key) {
            if (!property_exists($object, $key)) { $errors[] = $location.'.'.$key.': обязательное поле отсутствует.'; }
        }
        foreach (array_keys(get_object_vars($object)) as $key) {
            if (!in_array($key, $allowed, true)) { $errors[] = $location.'.'.$key.': неизвестное поле.'; }
        }
    }
}
