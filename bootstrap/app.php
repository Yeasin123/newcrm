<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAgent;
use App\Http\Middleware\EnsurePortalUser;
use App\Http\Middleware\SyncFailedPaymentSettings;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'permission' => CheckPermission::class,
            'portal' => EnsurePortalUser::class,
            'agent.auth' => EnsureAgent::class,
            'failed-payments.settings.sync' => SyncFailedPaymentSettings::class,
        ]);

        // Inbound webhooks post without a session / CSRF token.
        $middleware->preventRequestForgery(except: [
            'replit/claim/status',
            'telnyx/salesman-messages/webhook',
            'zero-entrance',
            'first/entrance',
            'second/entrance',
            'third/entrance',
            'fourth-entrance',
            'fifth-entrance',
            'sixth-entrance',
            'seventh-entrance',
            'eigth-entrance',
            'nineth-entrance',
            'mm-webhook',
            'new/yair-webhook/link',
            'avchad-webhook',
            'clicksend/get/notification',
            'ahc/campaign/lead-store',
            'ahw/campaign/lead-store',
            'lcl/campaign/lead-store',
            'gbl/campaign/lead-store',
            'jltwo/campaign/lead-store',
            'modanize/campaign/lead-store',
            'quote/email/webhook',
            'pquote/email/webhook',
            'cel/webhook',
            'campaign/webhook',
            'new/email/webhook',
            'second/new/webhook',
            'change/email/webhook',
            'vanilasoft/user/info',
            'check/salesman/id',
            'vanilasoft/viewboard/info',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
