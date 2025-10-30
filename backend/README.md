# Backend (Laravel 12 API)

주문 취합 인트라넷 **OF-Intranet**의 REST API 서버입니다.  
채널 관리, **엑셀 검증 규칙**, **엑셀 변환 프로필**을 제공합니다.

---

## 📦 요구사항
- PHP 8.4+
- Composer 2+
- MariaDB/MySQL
- (운영) **Nginx + PHP-FPM**

---

## 🚀 빠른 시작 (개발용)

```bash
cd backend
cp .env.example .env

# .env 편집 (예시)
# APP_URL=http://127.0.0.1:8000
# DB_CONNECTION=mariadb
# DB_DATABASE=orderfresh_intranet
# DB_USERNAME=...
# DB_PASSWORD=...
# CORS_ALLOWED_ORIGINS=http://127.0.0.1:5173

composer install
php artisan key:generate
php artisan migrate
php artisan storage:link   # 필요시
php artisan serve
```

프론트 개발 서버: `http://127.0.0.1:5173`  
API 기본 URL: **`http://127.0.0.1:8000/api/v1`**

---

## 🧩 핵심 도메인

### 1) Channels
- 필드: `code`, `name`, `is_excel_encrypted`, `excel_data_start_row`, `is_active`
- 관계: `channels` (1) — `channel_excel_validation_rules` (N)  
  `channels` (1) — `channel_excel_transform_profiles` (1)

### 2) Channel Excel Validation Rules
- 필드: **`cell_ref`**(A1 등), **`expected_label`**, **`is_required`**
- 목적: 채널별로 업로드되는 엑셀의 **헤더/셀 내용 검증**

### 3) Channel Excel Transform Profile (채널당 1건)
- 필드: **`tracking_col_ref`**(예: G 또는 G:G),  
  **`courier_name`**(nullable) / **`courier_code`**(nullable) → **둘 중 1개 필수**,  
  `template_notes`(nullable)
- 목적: 통합 엑셀 생성 시 **송장번호 열/택배사 정보** 주입을 위한 템플릿

> 마이그레이션에서 `courier_name`/`courier_code`는 **nullable** 이어야 합니다.

---

## 🗂️ 주요 폴더
```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   └── Requests/
│   └── Models/
├── database/migrations/
└── routes/api.php
```

---

## 🔌 API 개요

기본 프리픽스: **`/api/v1`**

### Channels
| 메서드 | 경로 | 설명 |
|---|---|---|
| GET | `/channels` | 목록 (검색은 `?q=` 지원 시) |
| POST | `/channels` | 생성 |
| GET | `/channels/{channel}` | 상세 |
| PUT | `/channels/{channel}` | 수정 (활성 토글 포함) |
| DELETE | `/channels/{channel}` | 삭제 |

### Channel Excel Validations
| 메서드 | 경로 | 설명 |
|---|---|---|
| GET | `/channels/{channel}/excel-validations` | 규칙 목록 |
| POST | `/channels/{channel}/excel-validations` | 규칙 생성 |
| PUT/PATCH | `/channels/{channel}/excel-validations/{rule}` | 규칙 수정 |
| DELETE | `/channels/{channel}/excel-validations/{rule}` | 규칙 삭제 |

### Channel Excel Transform Profile (Unique per Channel)
| 메서드 | 경로 | 설명 |
|---|---|---|
| GET | `/channels/{channel}/excel-transform` | 프로필 조회 |
| POST | `/channels/{channel}/excel-transform` | 생성 |
| PUT | `/channels/{channel}/excel-transform` | 수정 |
| DELETE | `/channels/{channel}/excel-transform` | 삭제 |

---

## ✅ 요청 검증 (요지)

### Store/UpdateChannelExcelTransformProfileRequest
```php
'tracking_col_ref' => ['required','regex:/^[A-Z]{1,3}(?::[A-Z]{1,3})?$/'],
'courier_name'     => ['nullable','string','max:50','required_without:courier_code'],
'courier_code'     => ['nullable','string','max:20','required_without:courier_name'],
'template_notes'   => ['nullable','string','max:255'],
```

### Validation Rule (예)
```json
{
  "cell_ref": "A1",
  "expected_label": "주문번호",
  "is_required": true
}
```

---

## 🧪 cURL 예시

### 채널 생성
```bash
curl -X POST http://127.0.0.1:8000/api/v1/channels   -H "Content-Type: application/json"   -d '{
    "name":"네이버 스마트스토어",
    "code":"smartstore",
    "is_excel_encrypted": true,
    "excel_data_start_row": 2,
    "is_active": true
  }'
```

### 엑셀 검증 규칙 생성
```bash
curl -X POST http://127.0.0.1:8000/api/v1/channels/1/excel-validations   -H "Content-Type: application/json"   -d '{
    "cell_ref":"A1",
    "expected_label":"주문번호",
    "is_required":true
  }'
```

### 엑셀 변환 프로필 생성 (이름만)
```bash
curl -X POST http://127.0.0.1:8000/api/v1/channels/1/excel-transform   -H "Content-Type: application/json"   -d '{
    "tracking_col_ref":"G",
    "courier_name":"우체국택배",
    "courier_code":"",
    "template_notes":"스마트스토어"
  }'
```

### 엑셀 변환 프로필 수정 (코드만)
```bash
curl -X PUT http://127.0.0.1:8000/api/v1/channels/1/excel-transform   -H "Content-Type: application/json"   -d '{
    "tracking_col_ref":"H:H",
    "courier_name":"",
    "courier_code":"9002"
  }'
```

---

## 🛠️ 운영 배포 (Nginx + PHP-FPM)

### 1) 디렉토리
- 코드: `/var/www/of-intranet/backend`
- 도큐먼트 루트: `/var/www/of-intranet/backend/public`
- 퍼미션:
  ```bash
  sudo chown -R www-data:www-data storage bootstrap/cache
  sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
  sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
  ```

### 2) Nginx 서버 블록 예시
```nginx
server {
    listen 80;
    server_name your.domain;

    root /var/www/of-intranet/backend/public;
    index index.php;

    client_max_body_size 20m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;  # PHP 버전에 맞게 조정
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.\.(?:htaccess|git|env)\$ {
        deny all;
    }

    # 정적 캐싱(선택)
    location ~* \.(?:css|js|jpg|jpeg|png|gif|svg|woff2?)$ {
        expires 7d;
        access_log off;
    }
}
```

### 3) Laravel 최적화
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### 4) CORS
- `config/cors.php`에서 프론트 도메인 허용 (예: `https://intra.orderfresh.co.kr`)

---

## 🧯 트러블슈팅

- **`BadMethodCallException ... excelTransformProfile()`**  
  → `App\Models\Channel`에 `hasOne(ChannelExcelTransformProfile::class)` 정의 필요.

- **FK/마이그레이션 오류**  
  → 테이블 생성 순서/DB명 확인. 안 되면 `php artisan migrate:fresh` 고려.

- **Unique 인덱스 이름 길이 초과**  
  → 인덱스명 짧게 변경.

- **422 Validation Error**  
  → `tracking_col_ref` 패턴, `courier_name/courier_code` 둘 중 1개 필수 확인.

---

## 📄 라이선스
사내 인트라넷 프로젝트. 무단 복제/배포 금지.
