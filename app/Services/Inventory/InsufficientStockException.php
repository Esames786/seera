<?php

namespace App\Services\Inventory;

use RuntimeException;

/**
 * Raised when a movement would push a warehouse balance below zero.
 * Negative stock is blocked by default in this phase.
 */
class InsufficientStockException extends RuntimeException
{
}
