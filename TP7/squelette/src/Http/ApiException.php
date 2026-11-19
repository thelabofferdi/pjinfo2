<?php
declare(strict_types=1);

namespace App\Http;

use RuntimeException;

// Erreur métier avec son code HTTP. Le message est destiné au client : pas de détail interne.
final class ApiException extends RuntimeException
{
    public function __construct(public readonly int $statutHttp, string $message)
    {
        parent::__construct($message);
    }
}
