<?php

namespace App\Jobs;

use App\Models\DataHubProxy;
use App\Services\Proxy\ProxyMonitorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshProxyAfterReset implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(
        public int $proxyId,
    ) {}

    public function handle(ProxyMonitorService $service): void
    {
        $proxy = DataHubProxy::query()
            ->where('is_active', true)
            ->find($this->proxyId);

        if (! $proxy) {
            return;
        }

        $service->refreshProxy($proxy);
    }
}
