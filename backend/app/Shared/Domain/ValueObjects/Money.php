<?php

namespace App\Shared\Domain\ValueObjects;

readonly class Money
{
    public function __construct(
        public float $amount,
        public string $currency = 'USD'
    ) {}
}
