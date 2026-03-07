<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Avalanche CRM Plugin Routes
|--------------------------------------------------------------------------
|
| Webhook and API routes for the Avalanche CRM plugin.
|
*/

Route::post('avalanchecrm/webhook/stripe', [\AvalancheStudio\AvalancheCRM\Http\StripeWebhookController::class, 'handle'])
    ->middleware('web')
    ->name('avalanchecrm.webhook.stripe');

Route::post('avalanchecrm/webhook/gocardless', [\AvalancheStudio\AvalancheCRM\Http\GoCardlessWebhookController::class, 'handle'])
    ->middleware('web')
    ->name('avalanchecrm.webhook.gocardless');

Route::post('avalanchecrm/webhook/paypal', [\AvalancheStudio\AvalancheCRM\Http\PayPalWebhookController::class, 'handle'])
    ->middleware('web')
    ->name('avalanchecrm.webhook.paypal');

/*
|--------------------------------------------------------------------------
| Client Invoice PDF Download
|--------------------------------------------------------------------------
*/
Route::get('avalanchecrm/invoice/{id}/pdf', function ($id) {
    $user = \Auth::getUser();
    if (!$user) {
        abort(403, 'Unauthorized');
    }

    $client = \AvalancheStudio\AvalancheCRM\Models\Client::where('user_id', $user->id)->first();
    if (!$client) {
        abort(403, 'No client profile found.');
    }

    $invoice = \AvalancheStudio\AvalancheCRM\Models\Invoice::where('id', $id)
        ->where('client_id', $client->id)
        ->firstOrFail();

    $pdf = \AvalancheStudio\AvalancheCRM\Classes\InvoicePdf::generate($invoice);

    return $pdf->download(\AvalancheStudio\AvalancheCRM\Classes\InvoicePdf::filename($invoice));
})->middleware('web')->name('avalanchecrm.invoice.pdf');

/*
|--------------------------------------------------------------------------
| Invoice Payments (Stripe, PayPal, GoCardless)
|--------------------------------------------------------------------------
*/
Route::prefix('avalanchecrm/invoice/{id}/payment')->middleware('web')->group(function () {
    // Initiate payment
    Route::get('stripe', [\AvalancheStudio\AvalancheCRM\Http\InvoicePaymentController::class, 'stripeCheckout'])
        ->name('avalanchecrm.invoice.pay.stripe');

    Route::get('paypal', [\AvalancheStudio\AvalancheCRM\Http\InvoicePaymentController::class, 'paypalCheckout'])
        ->name('avalanchecrm.invoice.pay.paypal');

    Route::get('gocardless', [\AvalancheStudio\AvalancheCRM\Http\InvoicePaymentController::class, 'gocardlessCheckout'])
        ->name('avalanchecrm.invoice.pay.gocardless');

    // PayPal capture callback (after approval)
    Route::get('paypal/capture', [\AvalancheStudio\AvalancheCRM\Http\InvoicePaymentController::class, 'paypalCapture'])
        ->name('avalanchecrm.invoice.pay.paypal.capture');

    // Success & cancel landing pages
    Route::get('success', [\AvalancheStudio\AvalancheCRM\Http\InvoicePaymentController::class, 'success'])
        ->name('avalanchecrm.invoice.pay.success');

    Route::get('cancel', [\AvalancheStudio\AvalancheCRM\Http\InvoicePaymentController::class, 'cancel'])
        ->name('avalanchecrm.invoice.pay.cancel');
});

/*
|--------------------------------------------------------------------------
| Marketing Email Unsubscribe
|--------------------------------------------------------------------------
*/
Route::get('avalanchecrm/unsubscribe/{token}', [\AvalancheStudio\AvalancheCRM\Http\UnsubscribeController::class, 'unsubscribe'])
    ->middleware('web')
    ->name('avalanchecrm.unsubscribe');

Route::get('avalanchecrm/resubscribe/{token}', [\AvalancheStudio\AvalancheCRM\Http\UnsubscribeController::class, 'resubscribe'])
    ->middleware('web')
    ->name('avalanchecrm.resubscribe');
