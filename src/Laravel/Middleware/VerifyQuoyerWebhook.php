<?php

declare(strict_types=1);

namespace Quoyer\Laravel\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Quoyer\Exceptions\SignatureVerificationException;
use Quoyer\Webhook;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies `X-Quoyer-Signature` against the raw body and hands the event to
 * the route as the `quoyer_event` request attribute:
 *
 *     Route::post('/webhooks/quoyer', QuoyerWebhookController::class)->middleware('quoyer.webhook');
 *
 *     $event = $request->attributes->get('quoyer_event'); // Quoyer\Resources\Event
 *
 * A bad signature is answered 400 and never reaches the route.
 */
final class VerifyQuoyerWebhook
{
    public function __construct(private readonly Repository $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = $this->config->get('quoyer.webhook.secret');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('Set QUOYER_WEBHOOK_SECRET (config quoyer.webhook.secret) to receive Quoyer webhooks.');
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->headers->get(Webhook::SIGNATURE_HEADER) ?? '',
                $secret,
                (int) $this->config->get('quoyer.webhook.tolerance', Webhook::DEFAULT_TOLERANCE),
            );
        } catch (SignatureVerificationException) {
            return new Response('Invalid Quoyer webhook signature.', 400);
        }

        $request->attributes->set('quoyer_event', $event);

        return $next($request);
    }
}
