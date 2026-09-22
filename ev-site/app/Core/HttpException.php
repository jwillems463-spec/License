<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public array $fields;
    /** Machine-readable error code for clients (e.g. "csrf_mismatch"). */
    public ?string $errorCode;

    public function __construct(int $status, string $message, array $fields = [], ?string $errorCode = null)
    {
        parent::__construct($message, $status);
        $this->fields = $fields;
        $this->errorCode = $errorCode;
    }
}
