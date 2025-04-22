<?php

namespace Csaba215\Mnb\Laravel\Facade;

use Csaba215\Mnb\Laravel\Client;
use Illuminate\Support\Facades\Facade;

class Mnb extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
