<?php

namespace App\Console\Commands;

use App\Services\Proxy\ProxyMonitorService;
use Illuminate\Console\Command;

class RefreshProxyData extends Command
{
    protected $signature = 'offorest:refresh-proxy-data';

    protected $description = 'Refresh proxy data hub sources and record changes.';

    public function handle(ProxyMonitorService $service): int
    {
        $results = $service->refreshAll();

        foreach ($results as $result) {
            $status = $result['changed'] ? 'CHANGED' : 'UNCHANGED';
            $this->line("[{$status}] {$result['name']} at {$result['checked_at']}");
        }

        return self::SUCCESS;
    }
}
