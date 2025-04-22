# Laravel-MNB

## Requirements
Package requires Soap, SimpleXML and version 8.1 of PHP or higher.

## Know-how
Please check out for corresponding documentations.

## Install
```bash
composer require csaba215/laravel-mnb
```

## Configuration
### Export
```bash
php artisan vendor:publish --provider="Csaba215\Mnb\Laravel\MnbServiceProvider" --tag="config"
```
### config/mnb-exchange.php
```php
    /*
     * Wsdl file location.
     * */
    'wsdl' => env('MNB_SOAP_WSDL', 'http://www.mnb.hu/arfolyamok.asmx?wsdl'),

    'cache' => [

        /*
         * Desired cache driver for service.
         * */
        'store' => env('MNB_CACHE_DRIVER', 'file'),

        /*
         * Minutes the cached currencies will be held for.
         * Default: 24hrs (1440)
         * */
        'minutes' => env('MNB_CACHE_MINUTES', 1440),
    ]
```

## Usage

### Access via facade
```php
use Csaba215\Mnb\Laravel\Facade\Mnb

$currency = Mnb::currentExchangeRate('EUR');
```

### Resolve by application container
```php
$currency = app(\Csaba215\Mnb\Laravel\Client::class)->currentExchangeRate('EUR');
```
### Access refresh date by reference
You can check the feed date by passing a $date variable to some methods.
These methods will make variable to be a Carbon instance.

```php
Mnb::exchangeRates($date);
$date->isToday();
```

### Available methods

#### Won't use cache
These methods won't use and update cache.
- currentExchangeRate($code, &$date = null): Currency
- currentExchangeRates(&$date = null): array of Currency

#### Will use cache
These methods will use cache.
- exchangeRate($code, &$date = null): single Currency
- exchangeRates(&$date = null): array of currencies
- currencies(): array of strings (each is currency code)
- hasCurrency($code): bool