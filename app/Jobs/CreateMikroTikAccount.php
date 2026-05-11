<?php

namespace App\Jobs;

use App\Models\InternetAccountRequest;
use App\Services\MikroTikService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateMikroTikAccount implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public InternetAccountRequest $internetAccountRequest,
        public string $semester,
        public string $termId,
        public string $username,
        public string $password,
    ) {}

    public function handle(MikroTikService $mikrotik): void
    {
        $this->internetAccountRequest->refresh();

        if ($this->internetAccountRequest->status === InternetAccountRequest::STATUS_CANCELLED) {
            Log::info('Skipping cancelled MikroTik internet account request', [
                'internet_account_request_id' => $this->internetAccountRequest->id,
                'username' => $this->username,
            ]);

            return;
        }

        $this->internetAccountRequest->update([
            'status' => InternetAccountRequest::STATUS_PROCESSING,
            'failure_reason' => null,
        ]);

        $response = $mikrotik->createInternetAccount(
            username: $this->username,
            password: $this->password,
            semester: $this->semester,
            termId: $this->termId,
        );

        $this->internetAccountRequest->update([
            'status' => InternetAccountRequest::STATUS_ACTIVE,
            'mikrotik_response' => $response,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('MikroTik internet account creation failed', [
            'internet_account_request_id' => $this->internetAccountRequest->id,
            'username' => $this->username,
            'semester' => $this->semester,
            'term_id' => $this->termId,
            'exception' => $exception ? $exception::class : null,
            'message' => $exception?->getMessage(),
        ]);

        $this->internetAccountRequest->update([
            'status' => InternetAccountRequest::STATUS_FAILED,
            'failure_reason' => $exception?->getMessage() ?? 'MikroTik account creation failed.',
        ]);
    }
}
