#!/usr/bin/env python3
"""Audit: kolom yang dipakai Api/* Inlislite vs kolom asli di DB.

Alias tabel dibatasi per-method agar tak tercemar method lain.
Pakai: python3 audit_api_columns.py
"""

import glob
import re
import subprocess

APP = "/home/yuzusa/Projects/Perpus/insilite/www/inlislitev33/app"


def db_cols():
    out = subprocess.run(
        [
            "mysql",
            "-u",
            "root",
            "-h",
            "127.0.0.1",
            "-P",
            "3307",
            "-N",
            "-e",
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS "
            "WHERE TABLE_SCHEMA='inlislite_v33';",
        ],
        capture_output=True,
        text=True,
    )
    tables = {}
    for line in out.stdout.splitlines():
        t, c = line.split("\t")
        tables.setdefault(t, set()).add(c.lower())
    return tables


TABLES = db_cols()
print(f"{len(TABLES)} tabel di DB")

CLAUSES = re.compile(
    r"->(?:select|where|orWhere|whereIn|orderBy|groupBy|like|orLike)\(\s*'(.*?)'",
    re.S,
)
TABLE_RE = re.compile(r"->table\(\s*'([A-Za-z_][\w]*)(?:\s+as\s+([A-Za-z_][\w]*))?'")
JOIN_RE = re.compile(r"->join\(\s*'([A-Za-z_][\w]*)(?:\s+as\s+([A-Za-z_][\w]*))?'")
TOKEN_RE = re.compile(r"\b([A-Za-z_][\w]*)\.([A-Za-z_][\w]*)\b")
FUNC_RE = re.compile(r"^\s*(?:public |private |protected )?function (\w+)")
KEYWORDS = {
    "as",
    "on",
    "and",
    "or",
    "like",
    "null",
    "true",
    "false",
    "desc",
    "asc",
    "left",
    "right",
    "inner",
    "outer",
    "join",
}

missing = []
for f in sorted(glob.glob(f"{APP}/Modules/**/Controllers/Api/*.php", recursive=True)):
    src = open(f).read()
    short = f.split("Modules/")[1]
    bounds = [
        m.start()
        for m in re.finditer(
            r"^\s*(?:public |private |protected )?function \w+", src, re.M
        )
    ]
    bounds.append(len(src))
    for i in range(len(bounds) - 1):
        block = src[bounds[i] : bounds[i + 1]]
        fm = FUNC_RE.match(block)
        mname = fm.group(1) if fm else "?"
        aliases = {}
        for m in TABLE_RE.finditer(block):
            aliases[(m.group(2) or m.group(1))] = m.group(1)
        for m in JOIN_RE.finditer(block):
            aliases[(m.group(2) or m.group(1))] = m.group(1)
        if not aliases:
            continue
        for m in CLAUSES.finditer(block):
            for alias, col in TOKEN_RE.findall(m.group(1)):
                if alias not in aliases or col.lower() in KEYWORDS:
                    continue
                table = aliases[alias]
                cols = TABLES.get(table)
                if cols is None:
                    missing.append((short, mname, f"{table}(TABEL TAK ADA)", col))
                elif col.lower() not in cols:
                    missing.append((short, mname, table, col))

seen = set()
for short, mname, table, col in missing:
    key = (table, col)
    if key in seen:
        continue
    seen.add(key)
    print(f"{table}.{col}  <- {short}::{mname}")
print(f"\nTotal kolom hilang (unik): {len(seen)}")
