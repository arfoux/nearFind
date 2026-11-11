<?php

namespace Database\Seeders;

use App\Models\Need;
use App\Models\Profile;
use Illuminate\Database\Seeder;

/**
 * The six example needs that used to live in `config/needs.php`, now real rows
 * in the `needs` table. Coordinates are around Sukajadi, Bandung, the area the
 * map opens on. Idempotent: re-running updates the row instead of duplicating
 * it (matched on title), so a seeded database and a fresh one look the same.
 */
class NeedSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private const ROWS = [
        [
            'title' => 'Jasa Laundry Kiloan',
            'marker_label' => 'Laundry Kiloan',
            'category' => 'household',
            'location' => 'Jl. Sukajadi No. 18, Sukajadi',
            'area' => 'Sukajadi, Bandung',
            'distance_km' => 0.8,
            'supporters' => 24,
            'description' => 'Warga perumahan kesulitan mencari laundry kiloan terdekat. Mereka harus menempuh lebih dari 2 km ke jalan utama. Layanan laundry kiloan di sekitar RW akan sangat membantu aktivitas harian warga.',
            'latitude' => -6.8905,
            'longitude' => 107.5944,
            'opportunity' => true,
            'hours_ago' => 52,
        ],
        [
            'title' => 'Jasa Servis AC',
            'marker_label' => 'Servis AC',
            'category' => 'repair',
            'location' => 'Jl. Setiabudi No. 112, Gegerkalong',
            'area' => 'Gegerkalong, Bandung',
            'distance_km' => 1.4,
            'supporters' => 19,
            'description' => 'Banyak warga membutuhkan teknisi AC langganan yang cepat datang, terutama saat musim panas. Saat ini warga harus menghubungi teknisi dari luar kelurahan.',
            'latitude' => -6.8875,
            'longitude' => 107.5978,
            'opportunity' => true,
            'hours_ago' => 5,
        ],
        [
            'title' => 'Bibit Tanaman',
            'marker_label' => 'Bibit Tanaman',
            'category' => 'retail',
            'location' => 'Jl. Surya Sumantri No. 45, Sukawarna',
            'area' => 'Sukawarna, Bandung',
            'distance_km' => 2.1,
            'supporters' => 15,
            'description' => 'Warga ingin menanam sayur di pekarangan, tetapi belum ada penjual bibit tanaman terdekat. Toko bibit keliling atau kios kecil akan membantu kebutuhan ini.',
            'latitude' => -6.8943,
            'longitude' => 107.5979,
            'opportunity' => true,
            'hours_ago' => 26,
        ],
        [
            'title' => 'Jasa Fotokopi',
            'marker_label' => 'Fotokopi',
            'category' => 'retail',
            'location' => 'Jl. Pasteur No. 8, Pasteur',
            'area' => 'Pasteur, Bandung',
            'distance_km' => 1.7,
            'supporters' => 12,
            'description' => 'Pelajar dan pekerja sekitar kesulitan mencetak serta memfotokopi dokumen. Layanan fotokopi dekat permukiman akan menghemat waktu warga.',
            'latitude' => -6.8912,
            'longitude' => 107.5908,
            'opportunity' => true,
            'hours_ago' => 9,
        ],
        [
            'title' => 'Warung Sayur Segar',
            'marker_label' => 'Warung Sayur',
            'category' => 'culinary',
            'location' => 'Jl. Sukajadi No. 76, Sukajadi',
            'area' => 'Sukajadi, Bandung',
            'distance_km' => 1.1,
            'supporters' => 31,
            'description' => 'Warga lansia membutuhkan warung sayur yang dekat dan menyediakan kebutuhan masak harian. Saat ini mereka harus berjalan jauh atau menunggu pedagang keliling.',
            'latitude' => -6.8936,
            'longitude' => 107.5924,
            'opportunity' => true,
            'hours_ago' => 74,
        ],
        [
            'title' => 'Klinik 24 Jam',
            'marker_label' => 'Klinik 24 Jam',
            'category' => 'health',
            'location' => 'Jl. Setiabudi No. 210, Gegerkalong',
            'area' => 'Gegerkalong, Bandung',
            'distance_km' => 1.9,
            'supporters' => 42,
            'description' => 'Belum ada klinik yang buka 24 jam di sekitar permukiman. Warga membutuhkan akses layanan kesehatan cepat, terutama pada malam hari dan akhir pekan.',
            'latitude' => -6.8868,
            'longitude' => 107.5910,
            'opportunity' => true,
            'hours_ago' => 8,
        ],
    ];

    public function run(): void
    {
        // A need belongs to a profile. The examples belong to the deployment
        // account when it exists, otherwise to a placeholder "Warga Sukajadi".
        $profile = Profile::where('email', (string) config('seal.auth.email'))->first()
            ?? Profile::where('username', 'warga_sukajadi')->first()
            ?? Profile::create([
                'email' => 'warga@nearfind.local',
                'name' => 'Warga Sukajadi',
                'username' => 'warga_sukajadi',
            ]);

        foreach (self::ROWS as $row) {
            $hoursAgo = (int) $row['hours_ago'];
            unset($row['hours_ago']);

            $need = Need::where('title', $row['title'])->first() ?? new Need;
            $need->fill($row + ['profile_id' => $profile->id, 'status' => 'aktif']);
            $need->created_at = now()->subHours($hoursAgo);
            $need->updated_at = now()->subHours($hoursAgo);
            $need->save();
        }
    }
}
