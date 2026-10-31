<?php
namespace App\Assessment\Port;

/** Future Course Service adapter; no implementation, automatic enrollment or quest mutation. */
interface LearningRecommendationPort
{
    /** @param list<string> $skillCodes @return list<array{title:string,reference:string}> */
    public function resourcesForSkills(array $skillCodes): array;
}
