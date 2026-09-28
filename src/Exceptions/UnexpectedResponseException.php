<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

use Quoyer\Http\ApiResponse;
use RuntimeException;

/**
 * The response was not what the API sends: an HTML error page, a redirect,
 * an empty body where JSON was due. Usually a wrong `base_url`, or a proxy
 * in the way.
 */
class UnexpectedResponseException extends RuntimeException implements QuoyerException
{
    public function __construct(string $message, private readonly ?ApiResponse $response = null)
    {
        parent::__construct($message, $response !== null ? $response->statusCode : 0);
    }

    public function getResponse(): ?ApiResponse
    {
        return $this->response;
    }
}
