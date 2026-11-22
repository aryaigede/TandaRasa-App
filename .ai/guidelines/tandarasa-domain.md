# TandaRasa — Aturan Domain

Aturan ini berasal dari FSD v1.1 (subbab 2.9.3, Tabel 2.8, FR-06 sampai FR-24). Jangan menyimpang tanpa persetujuan tim.

## Entri harian

- Satu pengguna punya paling banyak **satu entri per tanggal** (zona `Asia/Jakarta`). Terapkan dengan unique index `(user_id, entry_date)`.
- Entri berisi skor mood 1–5 (wajib) dan jurnal (opsional, paling banyak satu).
- Mengisi lagi di tanggal yang sama berarti **mengedit** entri tersebut, bukan membuat entri baru (FR-06, FR-07).
- Entri dari tanggal sebelumnya tidak bisa diedit.
- Entri disimpan lebih dulu, baru dianalisis. Kegagalan AI tidak boleh menggagalkan penyimpanan (FR-11, NFR-08).

## Sinyal risiko per entri

| Kondisi entri | Sumber sinyal |
| --- | --- |
| Ada jurnal | Modul AI (lihat `docs/ai-contract.md`) |
| Tanpa jurnal, skor 1 | `tinggi` |
| Tanpa jurnal, skor 2 | `sedang` |
| Tanpa jurnal, skor 3–5 | `rendah` |

Entri tanpa jurnal **tidak dikirim ke AI**.

## Tier (dihitung kode, bukan LLM)

Jendela = tanggal entri terbaru dan 6 tanggal sebelumnya (7 tanggal kalender).

1. **Merah**: minimal 3 *tanggal berbeda* di jendela dengan sinyal `tinggi`.
2. **Kuning**: bukan merah, dan sinyal entri terbaru `sedang` atau `tinggi`.
3. **Hijau**: selain itu.

Satu entri buruk tidak pernah memicu merah. Tanggal tanpa entri diabaikan (bukan dianggap rendah atau tinggi).

## Indikasi keselamatan (FR-24)

- `safety_flag` dari AI terpisah dari sinyal dan tier; tidak mengubah tier.
- Bila `true` pada entri terbaru: tampilkan pesan suportif, tautan Pusat Dukungan, dan kontak layanan darurat dari tabel sumber resmi, di halaman hasil entri itu, apa pun tiernya.
- Jangan menulis daftar kata pemicu di kode; deteksi dilakukan AI. Jangan mengirim notifikasi atau data apa pun ke pihak lain.
- Kontak layanan (konseling kampus, darurat) diambil dari tabel yang dikelola tim, berisi sumber resmi dan tanggal verifikasi. Jangan mengarang nomor telepon atau jam layanan.

## Respons per tier

| Tier | Yang ditampilkan |
| --- | --- |
| Hijau | Afirmasi ringan dan ajakan refleksi (FR-16) |
| Kuning | 1–3 micro-intervention, masing-masing < 15 menit, tanpa saran medis atau obat (FR-17) |
| Merah | Pesan suportif dan kontak serta jam layanan konseling kampus; keputusan menghubungi tetap di tangan mahasiswa (FR-18) |

Setiap hasil menampilkan alasan singkat dan keterangan "Hasil ini indikasi awal, bukan diagnosis." (FR-12, NFR-11, DoD06).

## Bahasa antarmuka

- Sederhana, hangat, tidak menghakimi, tidak klinis (NFR-02). Berlaku juga untuk pesan error.
- Jangan memakai kata "diagnosis", "gangguan", "penyakit", atau label klinis untuk menggambarkan pengguna.
- Label UI tier: "Stabil" (hijau), "Perlu perhatian" (kuning), "Butuh dukungan" (merah). Nama internal di kode tetap `hijau`/`kuning`/`merah`.
- Status tidak boleh dibedakan hanya lewat warna; selalu sertakan ikon dan label teks.

## Privasi

- Persetujuan registrasi (FR-03) mencakup penggunaan data, pemrosesan jurnal oleh penyedia AI pihak ketiga, dan pernyataan bukan diagnosis. Simpan versi teks persetujuan dan waktunya.
- Hapus akun menghapus seluruh entri, hasil analisis, dan persetujuan pengguna (FR-05, NFR-07).
- Isi jurnal tidak boleh muncul di log, notifikasi email, atau pesan error.
