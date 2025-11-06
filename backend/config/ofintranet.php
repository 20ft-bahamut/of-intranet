<?php

return [
    // 공유 폴더(자동 수집 VM이 떨구는 위치)
    'shared_folder' => env('OF_SHARED_FOLDER', '/srv/of-shared'),

    // 업로드(수동 업로드 임시 저장)
    'upload_disk' => env('OF_UPLOAD_DISK', 'local'),    // storage/app
    'upload_root' => env('OF_UPLOAD_ROOT', 'uploads'),  // storage/app/uploads

    // 파이썬 복호화
    'python_bin'  => env('OF_PYTHON_BIN', base_path('.venv/bin/python')),
    'decrypt_py'  => env('OF_DECRYPT_SCRIPT', base_path('scripts/decrypt_xlsx.py')),

    // 처리 후 보관
    'archive_root' => env('OF_ARCHIVE_ROOT', 'archive'), // storage/app/archive

    // 변환 미리보기 반환 행수
    'preview_rows' => 20,
];
