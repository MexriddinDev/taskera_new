<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Taskera Enterprise ITSM API',
    description: 'Taskera Enterprise ITSM API Documentation',
    contact: new OA\Contact(email: 'admin@taskera.uz')
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'Taskera API Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum'
)]
abstract class Controller
{
    //
}


