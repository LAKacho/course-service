<?php
namespace App\Integration;

/** Explicit failure contract for future adapters; no successful fallback is supplied. */
class IntegrationUnavailable extends \RuntimeException {}
