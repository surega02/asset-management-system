<?php

namespace App\Jobs;

use App\Actions\Imports\AssetImportBatches;
use App\Actions\Imports\ProcessImportRow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

final class ProcessAssetImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $batchId) {}

    public function handle(ProcessImportRow $processor): void
    {
        try {
            $this->process($processor);
        } catch (Throwable $exception) {
            // Queue exception storage must not capture SQL bindings or uploaded cell contents.
            throw new RuntimeException('Asset import batch '.$this->batchId.' interrupted ('.$exception::class.').');
        }
    }

    private function process(ProcessImportRow $processor): void
    {
        $started = hrtime(true);
        for ($count = 0; $count < 25 && hrtime(true) - $started < 40_000_000_000; $count++) {
            $claim = $processor->claim($this->batchId);
            if ($claim === null) {
                return;
            }
            $processor->execute($claim);
        }
        AssetImportBatches::dispatch($this->batchId);
    }
}
