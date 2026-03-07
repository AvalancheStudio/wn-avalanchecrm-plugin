<?php

namespace AvalancheStudio\AvalancheCRM\Http;

use Illuminate\Routing\Controller;
use AvalancheStudio\AvalancheCRM\Models\Client;

/**
 * Handles marketing email unsubscribe requests from clients.
 */
class UnsubscribeController extends Controller
{
    /**
     * Show the unsubscribe confirmation page and process the opt-out.
     *
     * @param string $token The client's unique unsubscribe token
     */
    public function unsubscribe(string $token)
    {
        $client = Client::where('unsubscribe_token', $token)->first();

        if (!$client) {
            return response()->view(
                'avalanchestudio.avalanchecrm::unsubscribe.invalid',
                [],
                404
            );
        }

        // Opt the client out of marketing
        $client->marketing_opt_out = true;
        $client->save();

        return response()->view(
            'avalanchestudio.avalanchecrm::unsubscribe.success',
            ['client' => $client]
        );
    }

    /**
     * Allow a client to re-subscribe via their token.
     *
     * @param string $token The client's unique unsubscribe token
     */
    public function resubscribe(string $token)
    {
        $client = Client::where('unsubscribe_token', $token)->first();

        if (!$client) {
            return response()->view(
                'avalanchestudio.avalanchecrm::unsubscribe.invalid',
                [],
                404
            );
        }

        $client->marketing_opt_out = false;
        $client->save();

        return response()->view(
            'avalanchestudio.avalanchecrm::unsubscribe.resubscribed',
            ['client' => $client]
        );
    }
}
