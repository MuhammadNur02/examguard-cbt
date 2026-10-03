"""Evaluasi akurasi skor esai (Task 5.3, PRD §13.2).

Pemakaian (dari folder nlp-service):
    python evaluasi.py dataset.csv [--keluar hasil.csv]

Dataset: CSV dari `php artisan ujian:ekspor-esai`, setelah kolom skor_dosen diisi
dosen. Kolom wajib: soal_id, bobot, kunci, jawaban, skor_dosen. Pemisah koma
atau titik koma; desimal titik atau koma. Baris tanpa skor_dosen dilewati.

Varian yang dibandingkan: stemming (ya/tidak) x korpus IDF (kunci + jawaban
pada soal yang sama / kunci saja). Untuk tiap varian:
    skor_sistem = similarity x bobot
    MAE         = rata-rata |skor_sistem - skor_dosen| (satuan skor)
    MAE (0-1)   = MAE setelah tiap skor dibagi bobot soalnya
    Pearson r   = korelasi skor ternormalisasi (sistem vs dosen)

Catatan: korpus IDF memakai jawaban yang ada di dataset. Bila dataset hanya
sebagian jawaban sebuah soal, IDF sedikit berbeda dari perhitungan di sistem.
"""

import argparse
import csv
import math
import sys
from collections import defaultdict
from dataclasses import dataclass
from pathlib import Path

from app.scoring import KorpusIdf, skor_esai

VARIAN: list[tuple[bool, KorpusIdf]] = [
    (True, "kunci_dan_jawaban"),
    (False, "kunci_dan_jawaban"),
    (True, "kunci"),
    (False, "kunci"),
]
KOLOM_WAJIB = {"soal_id", "bobot", "kunci", "jawaban", "skor_dosen"}


@dataclass(frozen=True)
class Baris:
    soal_id: str
    bobot: float
    kunci: str
    jawaban: str
    skor_dosen: float


def _angka(teks: str) -> float:
    return float(teks.strip().replace(",", "."))


def _teks(nilai: str) -> str:
    # Ekspor memberi awalan ' pada teks berawalan = + - @ agar aman di Excel.
    return nilai[1:] if nilai.startswith("'") else nilai


def baca_dataset(path: str | Path) -> tuple[list[Baris], int]:
    """Baca dataset; kembalikan baris bernilai dan jumlah baris yang dilewati."""
    with open(path, encoding="utf-8-sig", newline="") as berkas:
        contoh = berkas.read(4096)
        berkas.seek(0)
        dialek = csv.Sniffer().sniff(contoh, delimiters=",;")
        pembaca = csv.DictReader(berkas, dialect=dialek)
        kurang = KOLOM_WAJIB - set(pembaca.fieldnames or [])
        if kurang:
            raise ValueError(f"Kolom wajib tidak ada: {', '.join(sorted(kurang))}")

        baris: list[Baris] = []
        dilewati = 0
        for data in pembaca:
            if not (data["skor_dosen"] or "").strip():
                dilewati += 1
                continue
            baris.append(
                Baris(
                    soal_id=data["soal_id"].strip(),
                    bobot=_angka(data["bobot"]),
                    kunci=_teks(data["kunci"]),
                    jawaban=_teks(data["jawaban"]),
                    skor_dosen=_angka(data["skor_dosen"]),
                )
            )
    return baris, dilewati


def mae(a: list[float], b: list[float]) -> float:
    return sum(abs(x - y) for x, y in zip(a, b, strict=True)) / len(a)


def pearson(a: list[float], b: list[float]) -> float | None:
    """Korelasi Pearson; None bila n < 2 atau salah satu data konstan."""
    if len(a) < 2:
        return None
    rata_a = sum(a) / len(a)
    rata_b = sum(b) / len(b)
    kovarian = sum((x - rata_a) * (y - rata_b) for x, y in zip(a, b, strict=True))
    var_a = sum((x - rata_a) ** 2 for x in a)
    var_b = sum((y - rata_b) ** 2 for y in b)
    if var_a == 0 or var_b == 0:
        return None
    return kovarian / math.sqrt(var_a * var_b)


def skor_sistem(baris: list[Baris], stemming: bool, korpus_idf: KorpusIdf) -> list[float]:
    """Skor sistem per baris; IDF dihitung per soal (kunci + jawaban soal itu)."""
    per_soal: dict[str, list[int]] = defaultdict(list)
    for i, b in enumerate(baris):
        per_soal[b.soal_id].append(i)

    hasil = [0.0] * len(baris)
    for indeks in per_soal.values():
        kunci = baris[indeks[0]].kunci
        similarity = skor_esai(
            kunci, [baris[i].jawaban for i in indeks], stemming=stemming, korpus_idf=korpus_idf
        )
        for i, s in zip(indeks, similarity, strict=True):
            hasil[i] = round(s * baris[i].bobot, 2)
    return hasil


def evaluasi(baris: list[Baris]) -> list[dict]:
    dosen = [b.skor_dosen for b in baris]
    dosen_normal = [b.skor_dosen / b.bobot for b in baris]
    hasil = []
    for stemming, korpus in VARIAN:
        sistem = skor_sistem(baris, stemming, korpus)
        sistem_normal = [s / b.bobot for s, b in zip(sistem, baris, strict=True)]
        hasil.append(
            {
                "stemming": stemming,
                "korpus_idf": korpus,
                "n": len(baris),
                "mae": round(mae(sistem, dosen), 4),
                "mae_normal": round(mae(sistem_normal, dosen_normal), 4),
                "pearson": pearson(sistem_normal, dosen_normal),
                "skor": sistem,
            }
        )
    return hasil


def _nama_varian(stemming: bool, korpus: str) -> str:
    return f"{'stem' if stemming else 'tanpastem'}_{korpus}"


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Evaluasi MAE dan Pearson skor esai.")
    parser.add_argument("dataset", help="CSV ujian:ekspor-esai dengan skor_dosen terisi")
    parser.add_argument("--keluar", help="Tulis skor sistem per jawaban untuk tiap varian ke CSV")
    args = parser.parse_args(argv)

    baris, dilewati = baca_dataset(args.dataset)
    if not baris:
        print("Tidak ada baris dengan skor_dosen.", file=sys.stderr)
        return 1

    hasil = evaluasi(baris)
    print(f"Jumlah jawaban dinilai dosen: {len(baris)} (dilewati tanpa skor: {dilewati})\n")
    print("| Stemming | Korpus IDF | n | MAE | MAE (0-1) | Pearson r |")
    print("|---|---|---|---|---|---|")
    for h in hasil:
        r = "tidak terdefinisi" if h["pearson"] is None else f"{h['pearson']:.4f}"
        print(
            f"| {'ya' if h['stemming'] else 'tidak'} | {h['korpus_idf']} | {h['n']} | "
            f"{h['mae']:.4f} | {h['mae_normal']:.4f} | {r} |"
        )

    if args.keluar:
        with open(args.keluar, "w", encoding="utf-8-sig", newline="") as berkas:
            penulis = csv.writer(berkas)
            penulis.writerow(
                ["soal_id", "bobot", "skor_dosen"]
                + [_nama_varian(h["stemming"], h["korpus_idf"]) for h in hasil]
            )
            for i, b in enumerate(baris):
                penulis.writerow([b.soal_id, b.bobot, b.skor_dosen] + [h["skor"][i] for h in hasil])
    return 0


if __name__ == "__main__":
    sys.exit(main())
