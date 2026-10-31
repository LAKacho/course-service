<?php
namespace App\Service;
use App\Entity\Card;
class CardWorkflow
{
    public function changeStatus(Card $card, string $status): void {
        if (!in_array($status, Card::STATUSES, true)) { throw new \InvalidArgumentException('Неизвестный статус карточки.'); }
        $card->status = $status;
    }
}
