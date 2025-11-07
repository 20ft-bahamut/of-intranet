<?php

namespace App\Services\Excel;

use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class ExcelDecryptor
{
    private string $python;
    private string $script;

    public function __construct()
    {
        $this->python = config('ofintranet.python_bin');
        $this->script = config('ofintranet.decrypt_py');
    }

    public function decrypt(string $encryptedPath, ?string $password, int $startRow = 1): array
    {
        if (!is_file($encryptedPath)) {
            throw new \RuntimeException("file not found: {$encryptedPath}");
        }

        $outPrefix = sys_get_temp_dir() . '/ofx_' . uniqid();
        $cmd = [
            $this->python,
            $this->script,
            '--in', $encryptedPath,
            '--password', (string)$password,
            '--out', $outPrefix,
            '--start-row', (string)$startRow,
        ];

        $p = new Process($cmd);
        $p->setTimeout(180);
        $p->run();

        // ⚠ stderr 내용은 warning일 수도 있으니 일단 저장만 해둔다.
        $stderr = trim($p->getErrorOutput());
        if ($stderr !== '') {
            Log::warning('[decrypt warning]', [
                'stderr' => $stderr,
                'exit_code' => $p->getExitCode(),
                'cmd' => implode(' ', $cmd),
            ]);
        }

        // ✅ stdout에서 JSON만 추출
        $out = trim($p->getOutput());
        if (preg_match('/(\{.*\})/s', $out, $m)) {
            $jsonStr = $m[1];
        } else {
            $jsonStr = $out;
        }

        $json = json_decode($jsonStr, true);

        // ✅ JSON 안의 ok 필드가 true면 exitCode가 0이 아니어도 성공으로 본다.
        if ($json && !empty($json['ok'])) {
            return [
                'xlsx' => $json['xlsx'],
                'csv'  => $json['csv'],
                'json' => $json['json'],
            ];
        }

        // ❌ JSON이 없거나 ok=false일 때만 실패로 간주
        $msg = $json['error'] ?? $stderr ?: 'unknown decrypt error';
        throw new \RuntimeException('decrypt failed: ' . $msg);
    }
}
