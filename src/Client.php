<?php

namespace Csaba215\Mnb\Laravel;

use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use SimpleXMLElement;
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
        return (float)str_replace(',', '.', $number);
    }

    private function parseXml(string $xmlString): SimpleXMLElement
    {
        try {
            $xml = simplexml_load_string($xmlString);
        } catch (Exception $e) {
            throw new MnbException('Failed to parse XML response from SOAP API.', 0, $e, $xmlString);
        }

        if ($xml === false) {
            throw new Exception('Failed to parse XML response from SOAP API.');
        }

        return $xml;
    }

    /**
     * @param null|array<mixed> $data
     * @throws MnbException
     */
    private function getFromAPI(string $name, ?array $data = null): string
    {
        try {
            $xmlString = $this->client->{'Get' . $name}($data)->{'Get' . $name . 'Result'};
        } catch (Exception $e) {
            throw new MnbException('Failed to get XML from SOAP API.', 0, $e);
        }

        return $xmlString;
    }

    /**
     * Returns all available currencies from cache or from API if no cache is available.
     * Result is cached.
     *
     * @return string[]
     *
     * @throws MnbException on failure
     */
    public function currencies(): array
    {
        return $this->cache->remember(config('mnb-exchange.cache.key') . '.currencies', config('mnb-exchange.cache.minutes'),
            function () {
                $xmlString = $this->getFromAPI('Currencies');

                $xml = $this->parseXml($xmlString);

                $currencies = $xml->Currencies?->Curr;
                if ($currencies === null) {
                    throw new MnbException('Incorrect/Empty response from SOAP API.', 0, null, $xmlString);
                }

                return (array)$currencies;
            }
        );
    }

    /**
     * Determines if the currency is supported or not.
     * It uses cache or API if no cache is available. (Internally uses currencies method)
     *
     * @throws MnbException on failure
     */
    public function hasCurrency(string $currency): bool
    {
        return in_array($currency, $this->currencies());
    }

    /**
     * Returns the requested currency, from cache or from API if no cache is available.
     * If no date the latest date is fetched from cache or from API if no cache is available.
     * Result is cached.
     *
     * @return array{'rate': float, "unit": int}
     *
     * @throws MnbException on failure
     */
    public function exchangeRate(string $code, string|Carbon|DateTimeInterface|null $date = null): array
    {
        if ($date === null) {
            $date = $this->lastOpeningDate();
        }

        $date = $this->normalizeDate($date);

        return $this->cache->remember(config('mnb-exchange.cache.key') . ".currencies.rate.$code.$date", config('mnb-exchange.cache.minutes'),
            function () use ($date, $code) {
                $xmlString = $this->getFromAPI('ExchangeRates', ['startDate' => $date, 'endDate' => $date, 'currencyNames' => $code]);

                $xml = $this->parseXml($xmlString);

                return [
                    'rate' => $this->formatFloat((string)$xml->Day->Rate),
                    'unit' => (int)$xml->Day->Rate->attributes()->unit,
                ];
            }
        );
    }

    /**
     * Returns the current exchange rates for all available currencies from cache or from API if no cache is available.
     * Result is cached.
     *
     * @return array<string,array{rate: float, unit: int}>
     *
     * @throws MnbException on failure
     */
    public function currentExchangeRates(): array
    {
        return $this->cache->remember(config('mnb-exchange.cache.key') . '.current', config('mnb-exchange.cache.minutes'), function () {
            $xmlString = $this->getFromAPI('CurrentExchangeRates');

            $xml = $this->parseXml($xmlString);

            $rates = $xml->Day?->Rate;
            if ($rates === null) {
                throw new MnbException('Failed to parse response from SOAP API.', 0, null, $xmlString);
            }

            $current = [];
            foreach ($rates as $rate) {
                $current[(string)$rate->attributes()->curr] = [
                    'rate' => $this->formatFloat((string)$rate),
                    'unit' => (int)$rate->attributes()->unit,
                ];
            }

            return $current;
        });
    }

    /**
     * Return the latest opening date from cache or from API if no cache is available.
     * Result is cached.
     *
     * @throws MnbException on failure
     */
    public function lastOpeningDate(): string
    {
        return $this->cache->remember(config('mnb-exchange.cache.key') . '.end', config('mnb-exchange.cache.minutes'), function () {
            $xmlString = $this->getFromAPI('DateInterval');

            $xml = $this->parseXml($xmlString);

            $return = $xml->DateInterval->attributes()->enddate;
            if ($return === null) {
                throw new MnbException('Failed to parse response from SOAP API.', 0, null, $xmlString);
            }

            return (string)$return;
        });
    }

    /**
     * Return the first opening date from cache or from API if no cache is available.
     * Result is cached.
     *
     * @throws MnbException on failure
     */
    public function firstOpeningDate(): string
    {
        return $this->cache->remember(config('mnb-exchange.cache.key') . '.start', config('mnb-exchange.cache.minutes'), function () {
            $xmlString = $this->getFromAPI('DateInterval');

            $xml = $this->parseXml($xmlString);

            $return = $xml->DateInterval?->attributes()?->startdate;
            if ($return === null) {
                throw new MnbException('Failed to parse response from SOAP API.', 0, null, $xmlString);
            }

            return (string)$return;
        });
    }

    /**
     * Used for testing.
     */
    public function setClient(SoapClient $client): void
    {
        $this->client = $client;
    }

    /**
     * Used for testing.
     */
    public function setCache(Repository $cache): void
    {
        $this->cache = $cache;
    }
}
