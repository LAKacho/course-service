<?php
namespace App\Integration\Port;

use App\Integration\Contract\AuthenticatedIdentity;

/** Proposed application boundary; deliberately has no implementation or DI binding. */
interface AuthorizationPort
{
    /** Null means anonymous; transport unavailability must throw IntegrationUnavailable. */
    public function resolveCurrentIdentity(): ?AuthenticatedIdentity;
}
