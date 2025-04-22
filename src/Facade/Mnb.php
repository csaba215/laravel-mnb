<?php


namespace Csaba215\Mnb\Laravel\Facade;


use Illuminate\Support\Facades\Facade;
use Csaba215\Mnb\Laravel\Client;

class Mnb extends Facade {

    protected static function getFacadeAccessor()
    {
        return Client::class;
    }


}