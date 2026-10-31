<?php
namespace App\Integration\Port;

use App\Integration\Contract\LearningEvent;

/** Internal port to review with owners; does not claim an HTTP endpoint exists. */
interface NotificationPort
{
    /** Must not be called inside the learning-data transaction. */
    public function deliver(LearningEvent $event): void;
}
