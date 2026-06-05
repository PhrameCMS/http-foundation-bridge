<?php

declare(strict_types=1);

namespace PhrameCMS\HttpFoundationBridge;

use PhrameCMS\Core\Contracts\HttpTransportInterface;
use PhrameCMS\Core\Http\HttpMethod;
use PhrameCMS\Core\Http\Request;
use PhrameCMS\Core\Http\Response;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class HttpFoundationBridge implements HttpTransportInterface
{
    public static function isAvailable(): bool
    {
        return class_exists(SymfonyRequest::class) && class_exists(SymfonyResponse::class);
    }

    public static function requestFromGlobals(): Request
    {
        return (new self())->captureRequest();
    }

    public static function sendResponse(Response $response): void
    {
        (new self())->emitResponse($response);
    }

    public function captureRequest(): Request
    {
        return self::toCoreRequest(SymfonyRequest::createFromGlobals());
    }

    public function emitResponse(Response $response): void
    {
        self::toSymfonyResponse($response)->send();
    }

    private static function toCoreRequest(SymfonyRequest $request): Request
    {
        $body = $request->getContent();
        /** @var array<mixed, mixed>|null $jsonBody */
        $jsonBody = null;

        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                /** @var array<mixed, mixed> $decoded */
                $jsonBody = $decoded;
            }
        }

        /** @var array<string, mixed> $query */
        $query = $request->query->all();

        /** @var array<string, array<int, string>> $headers */
        $headers = $request->headers->all();

        return new Request(
            HttpMethod::fromString($request->getMethod()),
            $request->getPathInfo(),
            $query,
            self::flattenHeaders($headers),
            $jsonBody,
        );
    }

    private static function toSymfonyResponse(Response $response): SymfonyResponse
    {
        return new SymfonyResponse(
            $response->body(),
            $response->status(),
            $response->headers(),
        );
    }

    /**
     * @param array<string, array<int, string>> $headers
     *
     * @return array<string, string>
     */
    private static function flattenHeaders(array $headers): array
    {
        $flat = [];

        foreach ($headers as $name => $values) {
            $flat[$name] = implode(', ', $values);
        }

        return $flat;
    }
}
