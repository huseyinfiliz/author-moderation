<?php

namespace HuseyinFiliz\AuthorModeration\Middleware;

use HuseyinFiliz\AuthorModeration\Authority;
use HuseyinFiliz\AuthorModeration\Eligibility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CacheEligibility implements MiddlewareInterface
{
    public function __construct(
        protected Eligibility $eligibility,
        protected Authority $authority
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->eligibility->withinRequest(
            fn () => $this->authority->withinRequest(fn () => $handler->handle($request))
        );
    }
}
