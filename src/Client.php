<?php


namespace Csaba215\Mnb\Laravel;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use SoapClient;
use SoapFault;

class Client
{
    protected SoapClient $client;

    protected Repository $cache;

    /**
     * @throws SoapFault
     */
    public function __construct()
    {
        $this->client = new SoapClient(config('mnb-exchange.wsdl'));
        $this->cache = Cache::store(config('mnb-exchange.cache.store'));
    }

    /**
     * @return string[]
     */
    public function currencies(): array
    {
        return $this->cache->remember(config('mnb-exchange.cache.key').'.currencies', config('mnb-exchange.cache.minutes'),
            fn () => (array) simplexml_load_string($this->client->GetCurrencies()->GetCurrenciesResult)->Currencies->Curr
        );
    }

    /**
     * Determines if the currency is supported or not.
     */
    public function hasCurrency(string $currency): bool
    {
        return in_array($currency, $this->currencies());
    }

    /**
     * Will return with the cached exchange rate
     */
    public function exchangeRate(string $code, $date = null): array
    {
        if ($date === null) {
            $date = $this->lastOpeningDate();
        }

        return $this->cache->remember(config('mnb-exchange.cache.key').".currencies.rate.$code.$date", config('mnb-exchange.cache.minutes'),
            function () use ($date, $code) {
                $xml = simplexml_load_string($this->client->GetExchangeRates(['startDate' => $date, 'endDate' => $date, 'currencyNames' => $code])->GetExchangeRatesResult)->Day;

                return [
                    'rate' => (float) $xml->Rate,
                    'unit' => (int) $xml->Rate->attributes()->unit,
                    'curr' => (string) $xml->Rate->attributes()->curr,
                ];
            }
        );
    }

    /**
     * Will return with the current exchange rate
     */
    public function currentExchangeRates(): array
    {
        return $this->cache->remember(config('mnb-exchange.cache.key').'.current', config('mnb-exchange.cache.minutes'), function () {
            $current = [];
            foreach (simplexml_load_string($this->client->GetCurrentExchangeRates()->GetCurrentExchangeRatesResult)->Day->Rate as $rate) {
                $current[(string) $rate->attributes()->curr] = [
                    'rate' => (float) $rate,
                    'unit' => (int) $rate->attributes()->unit,
                ];
            }
            return $current;
        });
    }

    public function lastOpeningDate(): string
    {
        return (string) simplexml_load_string($this->client->GetDateInterval()->GetDateIntervalResult)->DateInterval->attributes()->enddate;
    }

    public function firstOpeningDate(): string
    {
        return (string) simplexml_load_string($this->client->GetDateInterval()->GetDateIntervalResult)->DateInterval->attributes()->startdate;
    }
}