# Prompt untuk Agen AI — TandaRasa

Salin prompt yang sesuai, ganti bagian dalam `<...>`. Agen sudah membaca `.ai/guidelines/` lewat Laravel Boost, jadi prompt cukup berisi tugasnya.

---

## 1. Orientasi (sekali di awal, atau saat agen baru dipakai)

```
Baca .ai/guidelines/tandarasa-project.md, .ai/guidelines/tandarasa-domain.md, dan docs/ai-contract.md.
Jangan menulis kode dulu.

Lalu jawab:
1. Ringkas aturan entri harian, sinyal risiko, dan tier dalam 5 poin.
2. Sebutkan aturan git dan atribusi yang harus kamu patuhi.
3. Periksa repo saat ini (struktur folder, .env.example, composer.json, migrasi yang ada) dan sebutkan apa saja yang belum sesuai dengan guidelines.
4. Daftar pertanyaan bila ada yang ambigu atau bertentangan.
```

## 2. Mengerjakan satu task (pakai untuk semua task ClickUp)

```
Task: <judul task dari ClickUp>
ID: <PBxx, FR-xx, NFR-xx>
Kriteria selesai (dari ClickUp):
<tempel kriteria selesai>

Bergantung pada: <task lain yang harus sudah ada, atau "tidak ada">
Di luar cakupan: <hal yang jangan dikerjakan di task ini>

Langkah:
1. Buat branch sesuai aturan di guidelines.
2. Tulis rencana: file yang dibuat/diubah, migrasi, dan daftar tes Pest yang memetakan tiap kriteria selesai. Berhenti dan tunggu persetujuanku.
3. Setelah aku setujui: tulis tesnya dulu, jalankan, pastikan gagal karena alasan yang benar.
4. Implementasi sampai semua tes lulus. Jalankan vendor/bin/pint dan seluruh tes.
5. Commit dengan pesan Conventional Commits yang menyebut ID requirement. Jangan tambahkan atribusi AI apa pun.
6. Tulis deskripsi PR dengan format di bagian 5 dokumen ini. Jangan push ke main dan jangan merge.
```

## 3. Mode belajar (agen membimbing, kamu yang menulis kode)

Pakai untuk bagian yang perlu kamu kuasai sendiri, misalnya bagian yang akan ditanya saat demo.

```
Task: <judul> (<ID>)
Kriteria selesai: <tempel>

Aku yang menulis kodenya. Kamu jangan mengedit file apa pun.
Pandu aku langkah demi langkah:
- Jelaskan satu langkah, termasuk kenapa langkah itu perlu, lalu tunggu aku mengerjakannya.
- Setelah aku bilang selesai, baca file yang kuubah dan beri koreksi spesifik (baris, masalah, alasannya).
- Jangan berikan solusi lengkap kecuali aku minta setelah mencoba.
```

## 4. Review PR anggota lain (DoD07)

```
Review branch <nama-branch> terhadap main untuk task <ID>.
Kriteria selesai: <tempel>

Periksa, urut dari yang paling penting:
1. Apakah setiap kriteria selesai benar-benar terpenuhi dan dites?
2. Otorisasi: adakah query entri/hasil yang tidak dibatasi user_id (FR-21)?
3. Privasi: adakah isi jurnal yang masuk log, error, atau dikirim bersama identitas ke AI (FR-20)?
4. Pelanggaran aturan domain (tier, entri harian, bahasa non-diagnosis).
5. Bug, kasus tepi, dan tes yang hilang.
Jangan mengedit kode. Tulis temuan sebagai daftar: lokasi, masalah, dampak, saran perbaikan.
```

## 5. Format deskripsi PR

```
## Task
<judul> — <PBxx / FR-xx>

## Perubahan
- ...

## Cara menguji
1. ...

## Checklist DoD
- [ ] DoD02 semua kriteria selesai terpenuhi dan dites
- [ ] DoD04 validasi input dan penanganan error
- [ ] DoD05 otorisasi per akun
- [ ] DoD06 hasil AI disertai keterangan bukan diagnosis (bila relevan)
- [ ] Pint dan seluruh tes lulus

## Catatan di luar cakupan
- ...
```

---

## Urutan task Sprint 1–2 untuk Arya

1. **Setup repository** (S1): starter kit, Boost, file guidelines ini, hook, CI. Pakai prompt 2 dengan kriteria "semua anggota bisa clone dan menjalankan proyek".
2. **Enum bersama** (bagian dari setup): `RiskSignal`, `Tier`, `AnalysisStatus` sesuai `docs/ai-contract.md` bagian 1, supaya Irfan bisa langsung mulai `TierCalculator`.
3. **Service analisis AI** (S2, PB04 FR-09): pakai `docs/ai-contract.md` bagian 3 sebagai kriteria selesai tambahan. Disarankan pakai prompt 3 (mode belajar) untuk bagian prompt dan validasi keluaran AI.
