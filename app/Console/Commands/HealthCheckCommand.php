<?php

namespace App\Console\Commands;

use App\Models\Link;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;

class HealthCheckCommand extends Command
{
    protected $signature = 'polr:health-check';

    protected $description = 'Crawls all links and checks http status codes';

    public function handle()
    {
        $links = Link::select('id', 'long_url')
            ->where('is_disabled', 0)
            ->whereNull('last_checked_at')
            ->orWhere('last_checked_at', '<', date('Y-m-d', strtotime('-30 days'))) // @todo make configurable
            ->get();

        $this->line('Links to check: '.$links->count());
        $client = new Client();

        foreach ($links as $link) {
            try {
                $headers = $client->head($link->long_url);
                $statusCode = $headers->getStatusCode();
            } catch (RequestException $ex) {
                $response = $ex->getResponse();
                $statusCode = $response ? $response->getStatusCode() : null;
            }

            $link->setHealth($statusCode);
            $link->save();

            $message = $link->long_url . ': ' . ($statusCode ?: 'connection error');
            if ($statusCode >= 200 && $statusCode < 300) {
                $this->info($message);
            } elseif ($statusCode >= 300 && $statusCode < 400) {
                $this->warn($message);
            } else {
                $this->error($message);
            }
        }
    }
}
