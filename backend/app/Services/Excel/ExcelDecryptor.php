<?php

namespace App\Services\Excel;

use Symfony\Component\Process\Process;
use RuntimeException;

class ExcelDecryptor
{
    public function __construct(
        private string $python = '',
        private string $script = '',
    ) {
        $this->python = config('ofintranet.python_bin');
        $this->script = config('ofintranet.decrypt_py');
    }

    /**
     * @return array{xlsx:string,csv:string,json:string}
     */
    public function decrypt(string $encryptedPath, string $password, int $startRow = 1): array
    {
        if (!is_file($encryptedPath)) {
            throw new RuntimeException("file not found: $encryptedPath");
        }

        $outPrefix = sys_get_temp_dir().'/ofx_'.uniqid();
        $cmd = [
            $this->python,
            $this->script,
            '--in', $encryptedPath,
            '--password', $password,           // 운영에서는 env/STDIN 방식 고려
            '--out', $outPrefix,
            '--start-row', (string) $startRow,
        ];

        $p = new Process($cmd, null, [
            // 민감정보를 환경변수로 넘기고 스크립트가 읽게 바꾸려면 여기에 넣을 것.
            // 'XLSX_PW' => $password
        ]);
        $p->setTimeout(120);
        $p->run();

        if (!$p->isSuccessful()) {
            throw new RuntimeException('decrypt failed: '.$p->getErrorOutput());
        }

        $json = json_decode($p->getOutput(), true);
        if (!is_array($json) || empty($json['ok'])) {
            $msg = is_array($json) ? ($json['error'] ?? 'unknown') : 'no json';
            throw new RuntimeException('decrypt script error: '.$msg);
        }
        return [
            'xlsx' => $json['xlsx'],
            'csv'  => $json['csv'],
            'json' => $json['json'],
        ];
    }
}
