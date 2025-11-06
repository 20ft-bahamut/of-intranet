#!/usr/bin/env python3
import argparse
import io
import json
import sys
from pathlib import Path
import msoffcrypto
import pandas as pd

def decrypt_xlsx(in_path, password):
    with open(in_path, "rb") as f:
        office = msoffcrypto.OfficeFile(f)
        if not office.is_encrypted():
            f.seek(0)
            return f.read()
        buf = io.BytesIO()
        office.load_key(password=password)
        office.decrypt(buf)
        return buf.getvalue()

def save_outputs(xlsx_bytes, out_prefix, start_row=1):
    out_xlsx = Path(f"{out_prefix}.xlsx")
    out_csv  = Path(f"{out_prefix}.csv")
    out_json = Path(f"{out_prefix}.json")

    out_xlsx.write_bytes(xlsx_bytes)
    try:
        df = pd.read_excel(out_xlsx, header=start_row - 1)
    except Exception:
        df = pd.read_excel(out_xlsx, header=None)
    df.columns = [str(c).strip() for c in df.columns]

    df.to_csv(out_csv, index=False, encoding="utf-8-sig")
    df.to_json(out_json, orient="records", force_ascii=False)
    return str(out_xlsx), str(out_csv), str(out_json)

def main():
    p = argparse.ArgumentParser()
    p.add_argument("--in", dest="infile", required=True)
    p.add_argument("--password", dest="password", required=True)
    p.add_argument("--out", dest="outprefix", required=True)
    p.add_argument("--start-row", dest="start_row", type=int, default=1)
    args = p.parse_args()

    try:
        xlsx_bytes = decrypt_xlsx(args.infile, args.password)
        xlsx_path, csv_path, json_path = save_outputs(xlsx_bytes, args.outprefix, args.start_row)
        print(json.dumps({"ok": True, "xlsx": xlsx_path, "csv": csv_path, "json": json_path}, ensure_ascii=False))
    except Exception as e:
        print(json.dumps({"ok": False, "error": str(e)}))
        sys.exit(2)

if __name__ == "__main__":
    main()
