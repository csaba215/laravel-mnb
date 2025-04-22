<?php

namespace Csaba215\Mnb\Laravel;

use Carbon\Carbon;
use DateTimeInterface;
use Exception;
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

    private function normalizeDate(string|Carbon|DateTimeInterface $date): string
    {
        return Carbon::parse($date)->format('Y-m-d');
    }

    private function formatFloat(string $number): float
    {
        return (float) str_replace(',', '.', $number);
    }

    /**
     * @return string[]
     */
    public function currencies(): array
    {
        return $this->cache->remember(config('mnb-exchange.cache.key').'.currencies', config('mnb-exchange.cache.minutes'),
            function () {
                $xml = simplexml_load_string($this->client->GetCurrencies()->GetCurrenciesResult);
                if ($xml === false) {
                    throw new Exception('Failed to parse XML response from SOAP API.');
                }

                $currencies = $xml->Currencies?->Curr;
                if ($currencies === null) {
                    throw new Exception('Incorrect/Empty response from SOAP API.');
                }

                return (array) $currencies;
            }
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
     *
     * @return array{'rate': float, "unit": int}
     *
     * @throws Exception
     */
    public function exchangeRate(string $code, string|Carbon|DateTimeInterface|null $date = null): array
    {
        if ($date === null) {
            $date = $this->lastOpeningDate();
        }

        $date = $this->normalizeDate($date);

        return $this->cache->remember(config('mnb-exchange.cache.key').".currencies.rate.$code.$date", config('mnb-exchange.cache.minutes'),
            function () use ($date, $code) {
                $xml = simplexml_load_string($this->client->GetExchangeRates(['startDate' => $date, 'endDate' => $date, 'currencyNames' => $code])->GetExchangeRatesResult);
                if ($xml === false) {
                    throw new Exception('Failed to parse XML response from SOAP API.');
                }

                return [
                    'rate' => $this->formatFloat((string) $xml->Day->Rate),
                    'unit' => (int) $xml->Day->Rate->attributes()->unit,
                ];
            }
        );
    }

    /**
     * Will return with the current exchange rate
     *
     * @return array<string,array{rate: float, unit: int}>
     */
    public function currentExchangeRates(): array
    {
        return $this->cache->remember(config('mnb-exchange.cache.key').'.current', config('mnb-exchange.cache.minutes'), function () {
            $xml = simplexml_load_string($this->client->GetCurrentExchangeRates()?->GetCurrentExchangeRatesResult);
            if ($xml === false) {
                throw new Exception('Failed to parse XML response from SOAP API.');
            }

            $rates = $xml->Day->Rate;
            if ($rates === null) {
                throw new Exception('Failed to parse XML response from SOAP API.');
            }

            $current = [];
            foreach ($rates as $rate) {
                $current[(string) $rate->attributes()->curr] = [
                    'rate' => $this->formatFloat((string) $rate),
                    'unit' => (int) $rate->attributes()->unit,
                ];
            }

            return $current;
        });
    }

    public function lastOpeningDate(): string
    {
        $xml = simplexml_load_string($this->client->GetDateInterval()->GetDateIntervalResult);
        if ($xml === false) {
            throw new Exception('Failed to parse XML response from SOAP API.');
        }

        return (string) $xml->DateInterval->attributes()->enddate;
    }

    public function firstOpeningDate(): string
    {
        $xml = simplexml_load_string($this->client->GetDateInterval()->GetDateIntervalResult);
        if ($xml === false) {
            throw new Exception('Failed to parse XML response from SOAP API.');
        }

        return (string) $xml->DateInterval->attributes()->startdate;
    }

    public function setClient(SoapClient $client): void
    {
        $this->client = $client;
    }

    public function setCache(Repository $cache): void
    {
        $this->cache = $cache;
    }
}
