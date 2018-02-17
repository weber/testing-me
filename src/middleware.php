<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);
$app->add(function ($request, $response, $next) {
    /*$response->getBody()->write('BEFORE');

    $response->getBody()->write('AFTER');*/
    $response = $next($request, $response);
    return $response;
});

