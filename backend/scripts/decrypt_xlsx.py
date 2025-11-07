#!/usr/bin/env python3
import argparse
import io
import json
import sys
import os
import warnings
from pathlib import Path
import msoffcrypto
import pandas as pd

# ✅ openpyxl 경고 숨기기 + stderr 무음
warnings.filterwarnings("ignore", category=UserWarning)
sys.stderr = open(os.devnull, 'w')


def decrypt_xlsx(in_path, password):
    """암호화된 xlsx 파일을 복호화하여 bytes 로 반환"""
    with open(in_path, "rb") as f:
        office = msoffcrypto.OfficeFile(f)
        if not office.is_encrypted():
            # 암호화 안된 파일은 그대로 리턴
            f.seek(0)
            return f.read()

        buf = io.BytesIO()
        office.load_key(password=password)
        office.decrypt(buf)
        return buf.getvalue()


def convert_xls_to_xlsx(xls_path, out_path):
    """(옵션) .xls → .xlsx 변환"""
    try:
        import xlrd, xlsxwriter
        book = xlrd.open_workbook(xls_path, logfile=open(os.devnull, 'w'))
        sheet = book.sheet_by_index(0)
        workbook = xlsxwriter.Workbook(out_path)
        worksheet = workbook.add_worksheet()
        for r in range(sheet.nrows):
            for c in range(sheet.ncols):
                worksheet.write(r, c, sheet.cell_value(r, c))
        workbook.close()
    except Exception as e:
        raise RuntimeError(f"xls→xlsx 변환 실패: {e}")


def save_outputs(xlsx_bytes, out_prefix, start_row=1):
    """복호화된 내용을 파일로 저장하고 csv/json으로도 출력"""
    out_xlsx = Path(f"{out_prefix}.xlsx")
    out_csv  = Path(f"{out_prefix}.csv")
    out_json = Path(f"{out_prefix}.json")

    # 복호화된 bytes 저장
    out_xlsx.write_bytes(xlsx_bytes)

    try:
        df = pd.read_excel(out_xlsx, header=start_row - 1)
    except Exception:
        df = pd.read_excel(out_xlsx, header=None)

    # ✅ 컬럼 정리
    df.columns = [str(c).strip() for c in df.columns]

    # ✅ 중복 컬럼 자동 리네이밍
    seen = {}
    newcols = []
    for c in df.columns:
        if c in seen:
            seen[c] += 1
            newcols.append(f"{c}_{seen[c]}")
        else:
            seen[c] = 0
            newcols.append(c)
    df.columns = newcols

    # ✅ CSV / JSON 저장
    df.to_csv(out_csv, index=False, encoding="utf-8-sig")
    df.to_json(out_json, orient="records", force_ascii=False)

    return str(out_xlsx), str(out_csv), str(out_json)


def main():
    parser = argparse.ArgumentParser(description="Decrypt Excel file (.xlsx or .xls)")
    parser.add_argument("--in", dest="infile", required=True)
    parser.add_argument("--password", dest="password", required=True)
    parser.add_argument("--out", dest="outprefix", required=True)
    parser.add_argument("--start-row", dest="start_row", type=int, default=1)
    args = parser.parse_args()

    in_path = args.infile
    password = args.password
    out_prefix = args.outprefix
    start_row = args.start_row

    try:
        # ✅ .xls 파일은 변환 후 경로 교체
        if in_path.lower().endswith(".xls"):
            tmp_xlsx = f"{out_prefix}_conv.xlsx"
            convert_xls_to_xlsx(in_path, tmp_xlsx)
            in_path = tmp_xlsx

        # ✅ 복호화 수행
        xlsx_bytes = decrypt_xlsx(in_path, password)

        # ✅ 출력 생성
        xlsx_path, csv_path, json_path = save_outputs(xlsx_bytes, out_prefix, start_row)

        print(json.dumps({
            "ok": True,
            "xlsx": xlsx_path,
            "csv": csv_path,
            "json": json_path
        }, ensure_ascii=False))

        sys.exit(0)

    except Exception as e:
        print(json.dumps({"ok": False, "error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
