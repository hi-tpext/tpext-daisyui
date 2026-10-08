<?php

namespace tpext\builder\webman;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use tpext\builder\common\Builder;

/**
 * for webman
 */

class Init implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        Builder::destroyInstance();
        $response = $next($request);
        Builder::destroyInstance();

        return $response;
    }
}
