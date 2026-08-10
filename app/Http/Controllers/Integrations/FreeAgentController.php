<?php

namespace App\Http\Controllers\Integrations;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentClient;
use App\Domains\Accounting\FreeAgent\Services\FreeAgentOAuthService;
use App\Http\Controllers\Controller;
use App\Models\ExternalConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FreeAgentController extends Controller
{
    public function connect(
        FreeAgentOAuthService $oauth
    ): RedirectResponse {
        return redirect()->away(
            $oauth->authorizationUrl()
        );
    }

    public function callback(
        Request $request,
        FreeAgentOAuthService $oauth
    ): RedirectResponse {
        abort_unless(
            hash_equals(
                (string) session('freeagent_oauth_state'),
                (string) $request->string('state')
            ),
            403,
            'Invalid OAuth state.'
        );

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $oauth->connect(
            (string) $request->string('code')
        );

        $request->session()->forget(
            'freeagent_oauth_state'
        );

        return redirect('/integrations/freeagent/health');
    }

    public function health(
        FreeAgentClient $client
    ): Response {
        $connection = ExternalConnection::where(
            'provider',
            'freeagent'
        )->firstOrFail();

        $response = $client->get(
            $connection,
            'company'
        );

        return response([
            'status' => 'connected',
            'provider' => 'freeagent',
            'company' => $response['company'] ?? $response,
        ]);
    }
}
