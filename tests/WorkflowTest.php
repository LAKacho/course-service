<?php
namespace App\Tests;
use App\Entity\{Card, Task};
use App\Service\{CardWorkflow, ProgressService};
use PHPUnit\Framework\TestCase;
class WorkflowTest extends TestCase
{
    public function testEmptyCourseAndRounding(): void {
        self::assertSame(0, ProgressService::percentage(0, 0));
        self::assertSame(33, ProgressService::percentage(3, 1));
        self::assertSame(100, ProgressService::percentage(3, 3));
    }
    public function testStatusChangeOnlyChangesCard(): void {
        $card = new Card(); $task = new Task(); $task->card = $card;
        (new CardWorkflow())->changeStatus($card, 'done');
        self::assertSame('done', $card->status);
        self::assertSame($card, $task->card);
    }
    public function testUnknownStatusIsRejected(): void {
        $this->expectException(\InvalidArgumentException::class);
        (new CardWorkflow())->changeStatus(new Card(), 'unknown');
    }
}
