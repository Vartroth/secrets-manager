<?php

declare(strict_types=1);

namespace Vartroth\SecretsManager\Exceptions;

use Exception;

class SecretNotFoundException extends Exception
{
    /**
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(string $message = "Secret not found", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
