<?php

namespace App\Modules\Fulfilment\Simulator;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The GST simulator (simulator.erudsway.com) through its integration API, authenticated with
 * an API key that has the `users` and `sso` scopes (simulator: php artisan integration:key-create).
 */
class SimulatorClient
{
    public function configured(): bool
    {
        return (string) config('services.simulator.url') !== '' && (string) config('services.simulator.key') !== '';
    }

    /**
     * Create a practice GST taxpayer login. Usernames and GSTINs are unique in the simulator,
     * so a clash is retried with fresh ones.
     *
     * @return array{id: int, username: string, password: string, gstin: string}
     */
    public function createGstUser(string $legalName): array
    {
        for ($attempt = 1; ; $attempt++) {
            $login = [
                'username' => 'erw'.Str::lower(Str::random(9)),
                'password' => Str::password(12, symbols: false),
                'gstin' => self::sampleGstin(),
            ];
            $response = $this->http()->post('/gst/users', [
                ...$login,
                'legal_name' => Str::limit(Str::upper(trim($legalName)) ?: 'ERUDSWAY LEARNER', 120, ''),
                'return_preference' => 'Monthly',
            ]);
            if ($response->status() === 422 && $attempt < 3 && preg_match('/username|gstin/i', (string) $response->body())) {
                continue;
            }
            try {
                $response->throw();
            } catch (RequestException $e) {
                throw new RuntimeException('Simulator refused the new login: HTTP '.$response->status().' '.Str::limit((string) $response->body(), 200), 0, $e);
            }

            return ['id' => (int) $response->json('id'), ...$login];
        }
    }

    /** A one-time link (valid about a minute) that signs the learner straight into their simulator login. */
    public function signInLink(string $username): string
    {
        $url = (string) $this->http()->post('/sso', ['app' => 'gst', 'user' => $username])->throw()->json('url');
        if ($url === '') {
            throw new RuntimeException('The simulator returned no sign-in link.');
        }

        return $url;
    }

    /** A made-up GSTIN in the right shape for the simulator: Tamil Nadu, random PAN-like body. */
    public static function sampleGstin(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $letters = fn (int $n) => implode('', array_map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)], range(1, $n)));

        return '33'.$letters(5).str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT).$letters(1).random_int(1, 9).'Z'.Str::upper(Str::random(1));
    }

    private function http(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('The GST simulator integration isn’t configured (SIMULATOR_URL / SIMULATOR_API_KEY).');
        }

        return Http::baseUrl(rtrim((string) config('services.simulator.url'), '/').'/api/integration/v1')
            ->withHeaders(['X-API-Key' => (string) config('services.simulator.key')])
            ->acceptJson()
            ->timeout(15);
    }
}
