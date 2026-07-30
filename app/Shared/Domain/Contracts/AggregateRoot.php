<?php

namespace App\Shared\Domain\Contracts;

interface AggregateRoot
{
    public function getRecordedEvents(): array;
    public function clearRecordedEvents(): void;
}
