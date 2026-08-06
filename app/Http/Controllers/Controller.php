<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(version: "1.0.0", title: "Payment Gateway API - E-Commerce Backend")]
#[OA\Server(url: "http://localhost:8000/api", description: "Local (tanpa tunnel)")]
#[OA\Server(url: "https://vegetarian-bloom-prospective-laid.trycloudflare.com/api", description: "Local via Cloudflare Tunnel")]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
abstract class Controller
{
    //
}