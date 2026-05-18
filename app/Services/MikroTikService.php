<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MikroTikService
{
    public function createInternetAccount(string $username, string $password, string $semester, string $termId): array
    {
        $host = (string) config('services.mikrotik.host');
        $apiUser = (string) config('services.mikrotik.user');
        $apiPass = (string) config('services.mikrotik.pass');
        $port = (int) config('services.mikrotik.port', 8728);
        $ssl = filter_var(config('services.mikrotik.ssl'), FILTER_VALIDATE_BOOLEAN);

        if (filled($host) && filled($apiUser)) {
            return $this->createViaRouterOsApi(
                host: $host,
                port: $port,
                useSsl: $ssl,
                apiUser: $apiUser,
                apiPass: $apiPass,
                username: $username,
                password: $password,
                semester: $semester,
                termId: $termId,
            );
        }

        $baseUrl = $this->resolveBaseUrl();

        if (blank($baseUrl)) {
            Log::warning('MikroTik account creation skipped because no endpoint is configured', [
                'username' => $username,
                'semester' => $semester,
                'term_id' => $termId,
            ]);

            return [
                'configured' => false,
                'message' => 'MikroTik endpoint is not configured.',
            ];
        }

        $request = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.mikrotik.timeout', 15))
            ->connectTimeout((int) config('services.mikrotik.connect_timeout', 5));

        $token = (string) config('services.mikrotik.token');
        $apiUser = (string) config('services.mikrotik.user');
        $apiPass = (string) config('services.mikrotik.pass');

        if (filled($token)) {
            $request = $request->withToken($token);
        } elseif (filled($apiUser)) {
            $request = $request->withBasicAuth($apiUser, $apiPass);
        }

        $response = $request
            ->post('/internet-accounts', [
                'username' => $username,
                'password' => $password,
                'semester' => $semester,
                'termId' => $termId,
            ])
            ->throw();

        return $response->json() ?? [];
    }

    private function createViaRouterOsApi(
        string $host,
        int $port,
        bool $useSsl,
        string $apiUser,
        string $apiPass,
        string $username,
        string $password,
        string $semester,
        string $termId
    ): array {
        $timeout = (float) config('services.mikrotik.connect_timeout', 5);
        $transport = $useSsl ? 'ssl' : 'tcp';
        $target = sprintf('%s://%s:%d', $transport, $host, $port);

        $socket = @stream_socket_client($target, $errno, $errstr, $timeout);
        if (! is_resource($socket)) {
            throw new RuntimeException(sprintf('Unable to connect to MikroTik API at %s (%s: %s)', $target, $errno, $errstr));
        }

        stream_set_timeout($socket, (int) config('services.mikrotik.timeout', 15));

        try {
            $this->writeSentence($socket, [
                '/login',
                '=name='.$apiUser,
                '=password='.$apiPass,
            ]);
            $loginReply = $this->readSentence($socket);
            if (($loginReply[0] ?? null) !== '!done') {
                throw new RuntimeException('MikroTik login failed.');
            }

            $comment = sprintf('Student Hub %s/%s', $semester, $termId);
            $server = (string) config('services.mikrotik.hotspot_server', 'all');
            $profile = (string) config('services.mikrotik.hotspot_profile', 'default');
            $this->writeSentence($socket, [
                '/ip/hotspot/user/add',
                '=server='.$server,
                '=name='.$username,
                '=password='.$password,
                '=profile='.$profile,
                '=comment='.$comment,
            ]);

            $addReply = $this->readSentence($socket);
            if (($addReply[0] ?? null) === '!trap') {
                $message = $this->findReplyValue($addReply, '=message=') ?? 'MikroTik rejected hotspot user creation.';
                throw new RuntimeException($message);
            }

            if (($addReply[0] ?? null) !== '!done') {
                throw new RuntimeException('Unexpected MikroTik response while creating hotspot user.');
            }

            return [
                'configured' => true,
                'transport' => $transport,
                'target' => $target,
                'username' => $username,
                'result' => 'created',
            ];
        } finally {
            fclose($socket);
        }
    }

    private function resolveBaseUrl(): ?string
    {
        $configuredBaseUrl = rtrim((string) config('services.mikrotik.base_url'), '/');
        if (filled($configuredBaseUrl)) {
            return $configuredBaseUrl;
        }

        $host = (string) config('services.mikrotik.host');
        if (blank($host)) {
            return null;
        }

        $ssl = filter_var(config('services.mikrotik.ssl'), FILTER_VALIDATE_BOOLEAN);
        $scheme = $ssl ? 'https' : 'http';
        $port = (int) config('services.mikrotik.port', $ssl ? 443 : 80);

        return sprintf('%s://%s:%d', $scheme, $host, $port);
    }

    private function writeSentence($socket, array $words): void
    {
        foreach ($words as $word) {
            $this->writeWord($socket, $word);
        }

        $this->writeWord($socket, '');
    }

    private function readSentence($socket): array
    {
        $words = [];
        while (true) {
            $word = $this->readWord($socket);
            if ($word === '') {
                break;
            }
            $words[] = $word;
        }

        return $words;
    }

    private function writeWord($socket, string $word): void
    {
        $this->writeLength($socket, strlen($word));
        fwrite($socket, $word);
    }

    private function readWord($socket): string
    {
        $length = $this->readLength($socket);
        if ($length === 0) {
            return '';
        }

        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Unexpected end of stream from MikroTik API.');
            }
            $data .= $chunk;
        }

        return $data;
    }

    private function writeLength($socket, int $length): void
    {
        if ($length < 0x80) {
            fwrite($socket, chr($length));
            return;
        }
        if ($length < 0x4000) {
            $length |= 0x8000;
            fwrite($socket, chr(($length >> 8) & 0xFF).chr($length & 0xFF));
            return;
        }
        if ($length < 0x200000) {
            $length |= 0xC00000;
            fwrite($socket, chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF));
            return;
        }
        if ($length < 0x10000000) {
            $length |= 0xE0000000;
            fwrite($socket, chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF));
            return;
        }

        fwrite($socket, chr(0xF0).chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF));
    }

    private function readLength($socket): int
    {
        $first = ord($this->readByte($socket));
        if (($first & 0x80) === 0x00) {
            return $first;
        }
        if (($first & 0xC0) === 0x80) {
            $second = ord($this->readByte($socket));
            return (($first & ~0xC0) << 8) + $second;
        }
        if (($first & 0xE0) === 0xC0) {
            $second = ord($this->readByte($socket));
            $third = ord($this->readByte($socket));
            return (($first & ~0xE0) << 16) + ($second << 8) + $third;
        }
        if (($first & 0xF0) === 0xE0) {
            $second = ord($this->readByte($socket));
            $third = ord($this->readByte($socket));
            $fourth = ord($this->readByte($socket));
            return (($first & ~0xF0) << 24) + ($second << 16) + ($third << 8) + $fourth;
        }

        $second = ord($this->readByte($socket));
        $third = ord($this->readByte($socket));
        $fourth = ord($this->readByte($socket));
        $fifth = ord($this->readByte($socket));

        return ($second << 24) + ($third << 16) + ($fourth << 8) + $fifth;
    }

    private function readByte($socket): string
    {
        $byte = fread($socket, 1);
        if ($byte === false || $byte === '') {
            throw new RuntimeException('Unexpected end of stream from MikroTik API.');
        }

        return $byte;
    }

    private function findReplyValue(array $reply, string $prefix): ?string
    {
        foreach ($reply as $word) {
            if (str_starts_with($word, $prefix)) {
                return substr($word, strlen($prefix));
            }
        }

        return null;
    }
}
