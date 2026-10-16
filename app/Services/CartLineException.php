<?php

namespace App\Services;

use RuntimeException;

/**
 * A cart line that cannot be sold as requested. $field is the offending input field.
 */
class CartLineException extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
