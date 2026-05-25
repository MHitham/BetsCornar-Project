<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'notifications' => $this->service->getAlerts(),
        ]);
    }
}
