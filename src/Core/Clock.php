<?php
declare(strict_types=1);

namespace Enlixo\Core;

/** Injected wherever "now" matters, so time-based rules can be tested. */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
