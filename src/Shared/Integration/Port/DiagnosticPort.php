<?php
namespace App\Integration\Port;

use App\Integration\Contract\{EvaluationReference, EvaluationHandle};

/** Proposed capability. No code, answer payload or external status vocabulary is assumed. */
interface DiagnosticPort
{
    /** Must not mark TaskProgress completed merely because delivery was accepted. */
    public function requestEvaluation(EvaluationReference $reference): EvaluationHandle;
}
