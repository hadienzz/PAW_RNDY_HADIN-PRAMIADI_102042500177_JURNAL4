<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\MataKuliah;
use App\Models\Material;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Dosen Pengampu Default
        $dosen = User::updateOrCreate(
            ['email' => 'dosen@celoe.test'],
            [
                'name' => 'Dr. Ir. Budi Santoso, M.T.',
                'password' => Hash::make('dosen123'),
                'role' => 'dosen',
                'nomor_induk' => '198501012015041001',
            ]
        );

        // 2. Akun Mahasiswa Default
        $mahasiswa1 = User::updateOrCreate(
            ['email' => 'mahasiswa@celoe.test'],
            [
                'name' => 'Ahmad Pratama',
                'password' => Hash::make('mahasiswa123'),
                'role' => 'mahasiswa',
                'nomor_induk' => '1301210001',
            ]
        );

        // 3. Mata Kuliah Dasar (Modul 3)
        $course1 = MataKuliah::updateOrCreate(
            ['kode_mk' => 'IF301'],
            [
                'nama_mk' => 'Pengembangan Aplikasi Website',
                'deskripsi' => 'Mata kuliah yang membahas arsitektur aplikasi web modern menggunakan framework Laravel, MVC, Eloquent ORM, dan keamanan web.',
                'sks' => 4,
                'semester' => 'Ganjil 2026/2027',
                'dosen_id' => $dosen->id,
            ]
        );

        // 4. Daftarkan Mahasiswa ke Mata Kuliah Pertama (status approved)
        $course1->students()->syncWithoutDetaching([
            $mahasiswa1->id => ['status' => 'approved'],
        ]);

        // 5. Tambahkan Materi Contoh
        Material::updateOrCreate(
            ['course_id' => $course1->id, 'minggu_ke' => 1, 'urutan' => 1],
            [
                'judul' => 'Pengenalan Arsitektur MVC & Routing di Laravel 12',
                'konten' => 'Mempelajari alur HTTP Request, Route Middleware, Controller, dan pembuatan View menggunakan Blade Template.',
            ]
        );

        // TODO 8: Tambahkan minimal 2 mata kuliah lagi dengan semester berbeda, 1 mahasiswa tambahan, 2 tugas, dan contoh data submission
        $mahasiswa2 = User::updateOrCreate(
            ['email' => 'siti@celoe.test'],
            [
                'name' => 'Siti Rahma',
                'password' => Hash::make('mahasiswa123'),
                'role' => 'mahasiswa',
                'nomor_induk' => '1301210002',
            ]
        );

        $course2 = MataKuliah::updateOrCreate(
            ['kode_mk' => 'IF204'],
            [
                'nama_mk' => 'Basis Data',
                'semester' => 'Ganjil 2026/2027',
                'sks' => 3,
                'dosen_id' => $dosen->id,
            ]
        );

        MataKuliah::updateOrCreate(
            ['kode_mk' => 'IF402'],
            [
                'nama_mk' => 'Pengujian Perangkat Lunak',
                'semester' => 'Genap 2026/2027',
                'sks' => 2,
                'dosen_id' => $dosen->id,
            ]
        );

        $course1->students()->syncWithoutDetaching([$mahasiswa2->id => ['status' => 'approved']]);
        $course2->students()->syncWithoutDetaching([$mahasiswa2->id => ['status' => 'approved']]);

        $tugasLaravel = Assignment::firstOrCreate(
            ['course_id' => $course1->id, 'judul' => 'Analisis Middleware Laravel'],
            [
                'deskripsi' => 'Jelaskan cara middleware auth dan role melindungi rute Laravel.',
                'deadline' => now()->addWeek(),
            ]
        );

        $tugasBasisData = Assignment::firstOrCreate(
            ['course_id' => $course2->id, 'judul' => 'Rancangan Relasi Basis Data'],
            [
                'deskripsi' => 'Jelaskan relasi antara mahasiswa, mata kuliah, dan tabel pendaftaran.',
                'deadline' => now()->addWeeks(2),
            ]
        );

        Submission::firstOrCreate(
            ['assignment_id' => $tugasLaravel->id, 'user_id' => $mahasiswa1->id],
            [
                'jawaban' => 'Middleware auth memeriksa sesi login, lalu middleware role membatasi akses berdasarkan peran pengguna.',
                'submitted_at' => now(),
                'nilai' => 88,
                'catatan_dosen' => 'Penjelasan tepat. Tambahkan contoh penggunaan pada rute.',
            ]
        );

        Submission::firstOrCreate(
            ['assignment_id' => $tugasBasisData->id, 'user_id' => $mahasiswa2->id],
            [
                'jawaban' => 'Mahasiswa dan mata kuliah terhubung melalui tabel pendaftaran dengan relasi banyak ke banyak.',
                'submitted_at' => now(),
            ]
        );
    }
}
