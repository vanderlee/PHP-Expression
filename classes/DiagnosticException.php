<?php

declare(strict_types=1);

namespace Vanderlee\Expression;

/** Machine-readable error with an optional zero-based byte offset. */
class DiagnosticException extends Exception
{
    private $errorCode;
    private $offset;

    public function __construct(string $errorCode, string $message, ?int $offset = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->offset = $offset;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }
}
