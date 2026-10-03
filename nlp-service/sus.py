"""Skor System Usability Scale (Task 5.5, PRD §13.4). Hanya pustaka standar.

Pemakaian:
    python sus.py respons.csv

CSV: kolom responden, peran, q1..q10 (skala 1-5), pemisah koma atau titik koma.
Skor tiap responden = (jumlah (q_ganjil - 1) + jumlah (5 - q_genap)) x 2,5,
rentang 0-100. Target PRD: rata-rata >= 68 (di atas rata-rata SUS).
"""

import argparse
import csv
import statistics
import sys
from pathlib import Path

AMBANG = 68.0


def skor_sus(jawaban: list[int]) -> float:
    if len(jawaban) != 10:
        raise ValueError("SUS membutuhkan tepat 10 jawaban.")
    if any(not 1 <= j <= 5 for j in jawaban):
        raise ValueError("Setiap jawaban SUS harus bernilai 1 sampai 5.")
    total = sum(j - 1 if i % 2 == 0 else 5 - j for i, j in enumerate(jawaban))
    return total * 2.5


def baca_respons(path: str | Path) -> list[tuple[str, str, float]]:
    with open(path, encoding="utf-8-sig", newline="") as berkas:
        dialek = csv.Sniffer().sniff(berkas.read(2048), delimiters=",;")
        berkas.seek(0)
        hasil = []
        for baris in csv.DictReader(berkas, dialect=dialek):
            jawaban = [int(baris[f"q{i}"]) for i in range(1, 11)]
            hasil.append((baris["responden"], baris["peran"].strip().lower(), skor_sus(jawaban)))
    return hasil


def _statistik(skor: list[float]) -> dict:
    return {
        "n": len(skor),
        "rata_rata": round(statistics.mean(skor), 2),
        "simpangan_baku": round(statistics.stdev(skor), 2) if len(skor) > 1 else 0.0,
        "minimum": min(skor),
        "maksimum": max(skor),
        "persen_di_atas_68": round(100 * sum(s >= AMBANG for s in skor) / len(skor), 1),
    }


def ringkas(data: list[tuple[str, float]]) -> dict[str, dict]:
    """Statistik per peran dan keseluruhan dari pasangan (peran, skor)."""
    hasil = {}
    for peran in sorted({p for p, _ in data}):
        hasil[peran] = _statistik([s for p, s in data if p == peran])
    hasil["semua"] = _statistik([s for _, s in data])
    return hasil


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Hitung skor SUS.")
    parser.add_argument("respons", help="CSV respons SUS")
    args = parser.parse_args(argv)

    respons = baca_respons(args.respons)
    if not respons:
        print("Tidak ada respons.", file=sys.stderr)
        return 1

    print("| Kelompok | n | Rata-rata | SD | Min | Maks | % >= 68 |")
    print("|---|---|---|---|---|---|---|")
    for kelompok, s in ringkas([(peran, skor) for _, peran, skor in respons]).items():
        print(
            f"| {kelompok} | {s['n']} | {s['rata_rata']:.2f} | {s['simpangan_baku']:.2f} | "
            f"{s['minimum']:.1f} | {s['maksimum']:.1f} | {s['persen_di_atas_68']:.1f} |"
        )
    print("\nSkor per responden:")
    for responden, peran, skor in respons:
        print(f"- {responden}: {skor:.1f} ({peran})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
