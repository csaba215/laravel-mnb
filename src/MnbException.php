<?php

namespace Csaba215\Mnb\Laravel;

use Exception;
use Throwable;

class MnbException extends Exception
{
    private ?string $xml;

    public function __construct(string $message, int $code = 0, ?Throwable $previous = null, ?string $xml = null)
    {
        parent::__construct($message, $code, $previous);
        $this->xml = $xml;
    }

    public function __toString(): string
    {
        return __CLASS__.": [{$this->code}]: {$this->message}\n";
    }

    public function getXML(): ?string
    {
        return $this->xml;
    }
}
