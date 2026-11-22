# TandaRasa — Konteks Proyek dan Aturan Kerja

## Produk

TandaRasa adalah aplikasi web self-monitoring untuk mahasiswa: mood check-in (skor 1–5) dan jurnal reflektif singkat, dianalisis AI, lalu sistem memberi respons bertingkat (tier hijau/kuning/merah). Ini **bukan alat diagnosis**.

Sumber kebenaran requirement: FSD v1.1 (`docs/requirements.md` bila sudah disalin ke repo). ID yang dipakai: FR-01 sampai FR-24, NFR-01 sampai NFR-13, PB01 sampai PB15, DoD01 sampai DoD08. Sebut ID ini di commit dan PR.

## Stack

- Laravel 13, Livewire 4, Alpine.js, Tailwind CSS (starter kit Livewire resmi, Flux UI).
- Basis data: **[TBD: MySQL/MariaDB atau PostgreSQL]**. Lokal boleh SQLite. Jangan pakai fitur SQL khusus satu engine.
- Antrean: `QUEUE_CONNECTION=database`. **Jangan tambahkan Redis.**
- Penjadwal: Laravel Scheduler (`routes/console.php`).
- AI: Laravel AI SDK (`laravel/ai`) dengan structured output. Penyedia: **[TBD]**. Kunci API hanya di `.env`.
- Tes: Pest. Format kode: Pint.
- Zona waktu aplikasi: `Asia/Jakarta`. "Satu hari" = tanggal kalender di zona ini.

## Arsitektur

- **Tidak ada REST API terpisah.** UI = komponen Livewire yang memanggil Action class. Nama task ClickUp yang menyebut "API" berarti logika backend di balik komponen Livewire.
- Logika bisnis di `app/Actions/` (satu kelas, satu aksi, method `handle()`), bukan di komponen Livewire.
- Enum di `app/Enums/` (`RiskSignal`, `Tier`, `AnalysisStatus`).
- Agen AI di `app/Ai/Agents/`, job analisis di `app/Jobs/`.
- Penentuan tier adalah kelas PHP murni tanpa I/O (lihat `docs/ai-contract.md`). LLM **tidak pernah** menentukan tier.
- Otorisasi lewat Policy dan query yang selalu dibatasi `user_id` milik pengguna login (FR-21). Jangan pernah mencari entri hanya berdasarkan `id` dari input pengguna.
- Teks yang tampil ke pengguna berbahasa Indonesia; identifier kode, nama tabel, dan kolom berbahasa Inggris.

## Perintah

- Jalankan lokal: `composer run dev`
- Tes: `php artisan test` (atau `vendor/bin/pest --filter=...`)
- Format: `vendor/bin/pint`
- Worker antrean (bila tidak lewat `composer run dev`): `php artisan queue:work`

## Alur kerja untuk setiap task

1. Baca task ID dan acceptance criteria yang diberikan. Bila ada yang ambigu atau bertentangan dengan FSD, **berhenti dan tanya**. Jangan menebak.
2. Tulis rencana singkat (file yang dibuat/diubah, migrasi, tes). Tunggu persetujuan sebelum menulis kode.
3. Tulis tes Pest dari acceptance criteria terlebih dahulu. Pastikan tes gagal karena alasan yang benar.
4. Implementasi sampai tes lulus. Jangan ubah tes agar lulus kecuali tesnya memang salah, dan jelaskan alasannya.
5. Jalankan `vendor/bin/pint` dan seluruh tes sebelum commit.
6. Kerjakan hanya cakupan task itu. Temuan di luar cakupan dicatat di deskripsi PR, bukan dikerjakan.

## Git

- Branch: `<tipe>/<PBxx>-<deskripsi-singkat>`, misalnya `feat/PB02-mood-checkin`.
- Commit: Conventional Commits dalam bahasa Indonesia, misalnya `feat(mood): simpan skor check-in harian (FR-06)`.
- **Atribusi:** jangan pernah menambahkan trailer `Co-Authored-By`, baris "Generated with ...", tautan sesi, atau penanda AI apa pun di pesan commit maupun deskripsi PR. Penulis commit adalah identitas git pemilik laptop (`git config user.name` / `user.email`); jangan mengubahnya.
- Jangan pernah memakai `git commit --no-verify`, `git push --force` ke branch bersama, atau push langsung ke `main`. Jangan merge PR. Review dan merge dilakukan anggota tim (DoD07).

## Definition of Done (ringkas)

DoD01 jalan di repo utama · DoD02 sesuai acceptance criteria · DoD03 tanpa bug kritis · DoD04 validasi input dan penanganan error · DoD05 otorisasi per akun · DoD06 hasil AI disertai keterangan bukan diagnosis · DoD07 diuji anggota lain · DoD08 terdokumentasi di commit/PR.

## Yang tidak boleh dilakukan

- Menambah dependensi baru tanpa menyebutkannya di rencana dan meminta persetujuan.
- Menyimpan atau mencatat (log) isi jurnal di luar tabel entri; jangan memasukkan isi jurnal ke log, pesan error, atau exception.
- Mengirim nama, email, atau ID akun ke penyedia AI (FR-20).
- Membuat fitur di luar cakupan semester: chat konseling real-time, forum/komunitas.
