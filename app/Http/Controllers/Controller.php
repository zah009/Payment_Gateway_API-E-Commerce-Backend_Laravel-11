<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(version: "1.0.0", title: "Payment Gateway API - E-Commerce Backend")]
#[OA\Server(url: "https://pools-equation-corn-lol.trycloudflare.com/api", description: "Local via Cloudflare Tunnel")]
#[OA\SecurityScheme(
    securityScheme: "sanctum",
    type: "http",
    scheme: "bearer",
    bearerFormat: "Sanctum Token"
)]
abstract class Controller
{
    //
}
