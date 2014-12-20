<?php

namespace Centipede;

use Centipede\Filter\UrlFilter;
use Centipede\Filter\FilterInterface;
use Centipede\Extractor\UrlExtractor;
use Centipede\Extractor\ExtractorInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Message\FutureResponse;

class Crawler
{
    private $baseUrl;
    private $depth;
    private $client;
    private $filter;
    private $extractor;

    public function __construct($baseUrl, $depth = 1)
    {
        $this->baseUrl = $baseUrl;
        $this->depth = $depth;

        $this->client = new Client();
        $this->filter = new UrlFilter();
        $this->extractor = new UrlExtractor();
    }

    public function crawl(callable $callable = null)
    {
        $urls = [$this->baseUrl];

        $response = $this->client->get($this->baseUrl, ['future' => true]);

        $this->doCrawl(
            $this->baseUrl,
            $response,
            $this->depth,
            $callable,
            $urls
        );

        $response->wait();

        return $urls;
    }

    public function setClient(ClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function setFilter(FilterInterface $filter)
    {
        $this->filter = $filter;

        return $this;
    }

    public function setExtractor(ExtractorInterface $extractor)
    {
        $this->extractor = $extractor;

        return $this;
    }

    private function doCrawl($url, FutureResponse $response, $depth, callable $callable = null, array &$urls = [])
    {
        if (null !== $callable) {
            $callable($url, $response);
        }

        if (0 === $depth) {
            return;
        }

        $response->then(function (Response $response) use ($url, $depth, $callable, &$urls) {
            $hrefs = $this->extractor->extract(
                $response->getBody()->getContents()
            );

            foreach ($hrefs as $href) {
                $href = $this->filter->filter($href);

                if (!in_array($href, $urls) && $this->shouldCrawl($href)) {
                    $this->doCrawl(
                        $href,
                        $this->client->get($href, ['future' => true]),
                        $depth - 1,
                        $callable,
                        $urls
                    );

                    $urls[] = $href;
                }
            }
        });
    }

    private function shouldCrawl($url)
    {
        if (empty($url)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (null === $host) {
            return true;
        }

        return $host === parse_url($this->baseUrl, PHP_URL_HOST);
    }
}
