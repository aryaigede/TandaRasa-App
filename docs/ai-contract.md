# Kontrak Modul AI dan Penentuan Tier

Versi: 1.0 · Berlaku untuk Sprint 2 (PB04, PB05)
Pemilik: Arya (service analisis AI) dan Irfan (logika tier). Perubahan kontrak harus disetujui keduanya.

Dokumen ini memisahkan dua pekerjaan supaya bisa dikerjakan paralel:

```
Entri disimpan ──► AnalyzeEntry (job, Arya) ──► sinyal per entri ──► TierCalculator (Irfan) ──► tier
                   └─ panggil AI hanya bila ada jurnal
```

## 1. Tipe bersama (dibuat sekali di Sprint 1, dipakai keduanya)

```php
enum RiskSignal: string { case Rendah = 'rendah'; case Sedang = 'sedang'; case Tinggi = 'tinggi'; }
enum Tier: string { case Hijau = 'hijau'; case Kuning = 'kuning'; case Merah = 'merah'; }
enum AnalysisStatus: string { case Pending = 'pending'; case Processing = 'processing'; case Done = 'done'; case Failed = 'failed'; }
```

## 2. Data hasil analisis per entri

Kolom minimum yang dibutuhkan kontrak ini (skema lengkap mengikuti ERD Kholis):

| Kolom            | Tipe                   | Keterangan                                   |
| ---------------- | ---------------------- | -------------------------------------------- |
| `entry_id`       | FK, unique             | Satu hasil per entri; analisis ulang menimpa |
| `status`         | `AnalysisStatus`       |                                              |
| `attempts`       | int                    | Jumlah percobaan panggilan AI                |
| `risk_signal`    | `RiskSignal`, nullable | Null selama belum `done`                     |
| `safety_flag`    | bool, default false    | FR-24                                        |
| `emotions`       | json, nullable         | Maks. 3 label                                |
| `reason`         | string, nullable       | Alasan singkat untuk pengguna                |
| `summary`        | text, nullable         | Ringkasan kondisi                            |
| `source`         | `ai` atau `mood_rule`  | Asal sinyal                                  |
| `prompt_version` | string, nullable       | Mis. `v1`; wajib bila `source = ai`          |
| `model`          | string, nullable       | Nama model yang menjawab                     |
| `analyzed_at`    | timestamp, nullable    |                                              |

## 3. Bagian Arya: AnalyzeEntry

### Pemicu

Dikirim ke antrean setiap kali entri harian dibuat atau diubah (FR-09). Bila entri diubah saat analisis lama masih berjalan, hasil lama tidak boleh menimpa hasil baru (bandingkan `updated_at` entri).

### Entri tanpa jurnal

Tidak memanggil AI. Sinyal dari skor: 1 → `tinggi`, 2 → `sedang`, 3–5 → `rendah`. `source = mood_rule`, `safety_flag = false`, `reason` dari teks tetap (bukan LLM).

### Masukan ke AI (minimisasi data, FR-20)

Hanya:

- Teks jurnal entri ini.
- Skor mood entri ini.
- Skor mood (angka saja) dari tanggal-tanggal lain di jendela 7 hari.

**Tidak dikirim:** nama, email, ID pengguna, ID entri, teks jurnal hari lain.

### Prompt

- Instruksi sistem menjelaskan peran (membaca refleksi mahasiswa, bukan mendiagnosis), definisi tiap nilai `risk_signal`, dan kapan `safety_flag = true` (indikasi keinginan menyakiti diri atau mengakhiri hidup).
- Teks jurnal dibungkus pembatas yang jelas dan dinyatakan sebagai data, bukan instruksi. Instruksi apa pun di dalam jurnal diabaikan.
- Simpan prompt di satu kelas agen; setiap perubahan isi prompt menaikkan `prompt_version`.

### Keluaran AI (structured output)

```json
{
    "risk_signal": "rendah | sedang | tinggi",
    "safety_flag": false,
    "emotions": ["lelah", "cemas"],
    "reason": "string, Bahasa Indonesia, maks. 200 karakter, tanpa istilah diagnosis",
    "summary": "string, Bahasa Indonesia, maks. 400 karakter"
}
```

Validasi sebelum disimpan. Keluaran yang tidak lolos dianggap **gagal** (masuk alur retry):

- `risk_signal` bukan salah satu dari tiga nilai.
- `safety_flag` bukan boolean.
- `emotions` lebih dari 3 item, atau `reason`/`summary` kosong atau melebihi batas.

### Kegagalan dan retry (FR-11, NFR-03, NFR-08)

- Entri sudah tersimpan sebelum job berjalan; job tidak pernah menghapus atau mengubah entri.
- Timeout panggilan AI: 10 detik.
- Job dicoba hingga 3 kali dengan jeda bertahap (nilai awal: 30 detik, 2 menit, 10 menit).
- Setelah percobaan habis: `status = failed`. UI menampilkan "analisis tertunda".
- Perintah terjadwal mencoba ulang hasil `failed` yang berumur < 24 jam (nilai awal: tiap 15 menit).
- Log hanya mencatat `entry_id`, status, dan jenis error. **Tidak pernah** isi jurnal atau isi respons AI.

### Setelah sinyal tersimpan

Panggil `TierCalculator` dengan sinyal di jendela 7 hari, lalu simpan tier untuk ditampilkan.

## 4. Bagian Irfan: TierCalculator

Kelas PHP murni: tanpa database, tanpa HTTP, tanpa waktu sistem. Semua masukan lewat parameter.

```php
final class TierCalculator
{
    /**
     * @param  array<string, RiskSignal>  $signalsByDate  kunci 'Y-m-d', hanya tanggal yang punya entri berstatus done
     * @param  string  $latestDate  'Y-m-d' entri terbaru
     */
    public function calculate(array $signalsByDate, string $latestDate): Tier;
}
```

Aturan:

1. Jendela = `$latestDate` dan 6 tanggal sebelumnya. Tanggal di luar jendela diabaikan.
2. Merah: minimal 3 tanggal di jendela bernilai `Tinggi`.
3. Kuning: bukan merah, dan sinyal `$latestDate` adalah `Sedang` atau `Tinggi`.
4. Hijau: selain itu.

Prasyarat: `$latestDate` wajib punya sinyal di `$signalsByDate` (analisisnya berstatus `done`), dan semua
kunci berformat `Y-m-d`. Bila tidak, kelas melempar `InvalidArgumentException`. Pemanggil yang entri
terbarunya masih `pending`/`failed` menampilkan "analisis tertunda", bukan tier.

`safety_flag` **tidak** masuk ke kelas ini.

## 5. Skenario uji wajib (NFR-12)

Semua tes memalsukan (fake) respons AI; tidak ada tes yang memanggil penyedia sungguhan dan tidak ada teks jurnal sensitif di fixture.

| #   | Masukan (sinyal per tanggal, H = hari terbaru)                                | Harapan                                                                                   |
| --- | ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| 1   | H-6..H semua `rendah`                                                         | Hijau                                                                                     |
| 2   | H `sedang`, lainnya `rendah`                                                  | Kuning                                                                                    |
| 3   | `tinggi` di H-4 dan H, lainnya `rendah` (2 hari)                              | Kuning, bukan merah                                                                       |
| 4   | `tinggi` di H-5, H-2, H                                                       | Merah                                                                                     |
| 5   | `tinggi` di H-7, H-3, H (H-7 di luar jendela)                                 | Kuning                                                                                    |
| 6   | H diedit dari `tinggi` ke `rendah`, `tinggi` di H-1 dan H-2                   | Hijau (2 hari tinggi, terbaru rendah)                                                     |
| 7   | Tiga tanggal tanpa jurnal dengan skor 1 (H-4, H-1, H)                         | Merah (lewat aturan skor)                                                                 |
| 8   | Respons AI palsu `safety_flag = true`, `risk_signal = rendah`, riwayat rendah | Tier Hijau **dan** Pusat Dukungan tampil                                                  |
| 9   | AI timeout                                                                    | Entri tersimpan, status `pending`/`failed`, UI "analisis tertunda", job dijadwalkan ulang |
| 10  | AI mengembalikan JSON tidak valid / enum salah                                | Diperlakukan sebagai gagal (seperti #9)                                                   |
| 11  | Dua pengguna, pengguna A meminta hasil entri B                                | Ditolak (FR-21)                                                                           |

Skenario 1–7 adalah tes unit `TierCalculator` (Irfan). Skenario 7–11 adalah tes fitur alur analisis (Arya). Hasil uji dicatat untuk task "Uji analisis AI pada minimal 5 skenario".
