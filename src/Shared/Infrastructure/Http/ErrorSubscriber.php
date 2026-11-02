<?php
namespace App\Shared\Infrastructure\Http;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Access\Application\Exception\PermissionDenied;
use App\Shared\Application\Exception\{Conflict,InvalidInput,ResourceNotVisible};
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Twig\Environment;
class ErrorSubscriber implements EventSubscriberInterface
{
    public function __construct(private Environment $twig) {}
    public static function getSubscribedEvents(): array { return ['kernel.exception' => ['onException', -64]]; }
    public function onException(ExceptionEvent $event): void {
        $error = $event->getThrowable();
        $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : match (true) { $error instanceof PermissionDenied => 403, $error instanceof ResourceNotVisible => 404, $error instanceof Conflict => 409, $error instanceof InvalidInput => 400, default => 500 };
        $message = match ($status) { 404 => 'Запись или страница не найдена.', 403 => 'Действие отклонено. Обновите страницу и повторите попытку.', 405 => 'Метод запроса не поддерживается.', 400 => 'Некорректные данные запроса.', default => 'Не удалось выполнить запрос. Проверьте подключение к базе данных и журнал приложения.' };
        $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
        if (str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            $event->setResponse(new JsonResponse(['error' => ['status' => $status, 'message' => $message]], $status, $headers));
        } else {
            $event->setResponse(new Response($this->twig->render('error.html.twig', ['status' => $status, 'message' => $message]), $status, $headers));
        }
    }
}
