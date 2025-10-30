# 🧾 OF-Intranet (Orderfresh Intranet System)

> 주식회사 **동해(Orderfresh)** 내부용 주문 취합 및 관리 시스템  
> 여러 판매 채널의 주문 엑셀을 통합·검증·변환하여 관리하는 **라라벨 + 스벨트 기반** 인트라넷 플랫폼입니다.

---

## 🚀 개요

각 온라인 채널(스마트스토어, 쿠팡, 농협몰, 우체국쇼핑 등)에서 다운로드된 주문 엑셀 파일을  
자동으로 수집하여 **단일 대시보드에서 검증·정규화·송장 자동 변환**까지 처리합니다.  

- 각 채널별 Excel 구조 및 암호화 규칙 정의  
- 주문 엑셀의 컬럼/값 검증 규칙 관리  
- 송장 자동 입력용 변환 프로필 생성  
- 주문/상품/채널 통합 관리 및 통계 제공  

---

## 🧩 기술 스택

| 영역 | 기술 |
|------|------|
| **Backend** | Laravel 12.36 / PHP 8.4 / MariaDB |
| **Frontend** | SvelteKit 2 / Svelte 5 / Bulma 1.0 / Material Icons |
| **통신 구조** | RESTful API (`/api/v1/…`) + JSON 응답 |
| **기타** | CORS 설정 / Laravel FormRequest 검증 / Bulma 모달 기반 Confirm UI |

---

## 🗂️ 프로젝트 구조

```
of-intranet/
├── backend/                # Laravel API 서버
│   ├── app/Models/
│   │   ├── Channel.php
│   │   ├── ChannelExcelValidationRule.php
│   │   └── ChannelExcelTransformProfile.php
│   ├── app/Http/Controllers/Api/V1/
│   ├── app/Http/Requests/
│   ├── database/migrations/
│   └── routes/api.php
│
└── frontend/               # SvelteKit 기반 대시보드 UI
    ├── src/routes/
    │   ├── +layout.svelte          # 공통 레이아웃 / GNB
    │   ├── +page.svelte            # 대시보드
    │   ├── channels/+page.svelte   # 채널 관리
    │   ├── channels/[id]/excel-validations/+page.svelte
    │   └── channels/[id]/excel-transform/+page.svelte
    └── src/lib/components/
        └── ConfirmModal.svelte
```

---

## ⚙️ 주요 기능

### 🏠 대시보드
- 오늘 / 어제 / 최근 7일 주문 건수
- 채널별 주문 분포 / 상품별 주문 TOP 10
- 최신 주문 스냅샷

### 🧩 채널 관리
- 채널 등록 / 수정 / 삭제 (활성화 토글)
- 검색(이름/코드)
- **엑셀 검증 룰** 및 **엑셀 변환 프로필**로 연동

### 📋 엑셀 검증 규칙 (Excel Validations)
- 채널별 엑셀 헤더 검증 셀 등록 (A1, B1 …)
- 필수 여부 설정(`is_required`)
- CRUD + Bulma Confirm 모달

### 🔄 엑셀 변환 프로필 (Excel Transform)
- 송장 자동입력용 엑셀 생성 설정
- `tracking_col_ref` 필수, `courier_name` / `courier_code` 중 하나는 필수
- 채널당 1건(UNIQUE)
- UI: 현재 상태 표시, 수정·삭제 가능

---

## 🧱 데이터베이스 구조 (요약)

| 테이블 | 설명 |
|---------|------|
| **channels** | 채널 기본정보 (`code`, `name`, `is_excel_encrypted`, `is_active`, `excel_data_start_row`) |
| **channel_excel_validation_rules** | 채널별 엑셀 헤더 검증 규칙 (`cell_ref`, `expected_label`, `is_required`) |
| **channel_excel_transform_profiles** | 채널별 송장 변환 프로필 (`tracking_col_ref`, `courier_name`, `courier_code`, `template_notes`) |

---

## 🔌 API 엔드포인트 (요약)

| 구분 | 메서드 | 경로 | 설명 |
|------|---------|------|------|
| 채널 | GET/POST/PUT/DELETE | `/api/v1/channels` | 채널 CRUD |
| 검증 룰 | GET/POST/PUT/DELETE | `/api/v1/channels/{id}/excel-validations` | 엑셀 헤더 검증 룰 |
| 변환 프로필 | GET/POST/PUT/DELETE | `/api/v1/channels/{id}/excel-transform` | 송장 변환 프로필 |

---

## 🧠 개발 컨벤션

- **HTML 시멘틱 구조** 엄수 (`<section>`, `<nav>`, `<table>`, `<form>` 등)
- **접근성(ARIA)**: 주요 영역 `aria-labelledby`, `aria-live="polite"`
- **폼 표준화**: 모든 `<input>`은 `id`/`label for` 연결, `required` 명시
- **버튼 타입 구분**: `button[type="button"]` / `button[type="submit"]`
- **모달 UX**: ESC 닫기, 포커스 트랩, 배경 클릭 닫기
- **Fetch 구조**: 모든 API 호출은 `try/catch/finally`
- **컴포넌트 네이밍**: `fetch*`, `save`, `remove`, `toggle*` 일관성 유지

---

## 🧭 실행 방법

```bash
# 1. 백엔드
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve

# 2. 프론트엔드
cd ../frontend
npm install
npm run dev
```

> 기본 URL  
> 백엔드: `http://127.0.0.1:8000`  
> 프론트: `http://127.0.0.1:5173`

---

## 🧑‍💻 제작자

**박하맛이긔 (BahamuT)**  
Fullstack PM · Developer  
> 📦 Orderfresh / 주식회사 동해  
> 📅 2025 — Present

---

## 📜 License
이 저장소는 사내 인트라넷 프로젝트용으로 **비공개(private)** 라이선스입니다.  
무단 복제·배포를 금합니다.
