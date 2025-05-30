<?php

return [

    /*
     * Wsdl file location.
     * */
    'wsdl' => env('MNB_SOAP_WSDL', 'https://mnb.hu/arfolyamok.asmx?wsdl'),

    'cache' => [

        /*
         * Desired cache driver for service.
         * */
        'store' => env('MNB_CACHE_DRIVER', 'file'),

        /*
         * Minutes the cached currencies will be held.
         * Default: 24hrs (1440)
         * */
        'minutes' => env('MNB_CACHE_MINUTES', 1440),

        /*
         * Desired cache key prefix.
         * */
        'key' => env('MNB_CACHE_KEY', 'mnb'),
    ],

];
