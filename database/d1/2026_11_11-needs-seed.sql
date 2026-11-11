INSERT INTO needs (profile_id, title, marker_label, category, location, description, area, latitude, longitude, distance_km, supporters, opportunity, status, created_at, updated_at)
SELECT p.id, 'Jasa Laundry Kiloan', 'Laundry Kiloan', 'household', 'Jl. Sukajadi No. 18, Sukajadi', 'Warga perumahan kesulitan mencari laundry kiloan terdekat. Mereka harus menempuh lebih dari 2 km ke jalan utama. Layanan laundry kiloan di sekitar RW akan sangat membantu aktivitas harian warga.', 'Sukajadi, Bandung', -6.8905, 107.5944, 0.8, 24, 1, 'aktif', datetime('now', '-52 hours'), datetime('now', '-52 hours')
FROM (SELECT id FROM profiles WHERE username = 'warga_sukajadi' LIMIT 1) AS p
WHERE NOT EXISTS (SELECT 1 FROM needs WHERE needs.title = 'Jasa Laundry Kiloan');

INSERT INTO needs (profile_id, title, marker_label, category, location, description, area, latitude, longitude, distance_km, supporters, opportunity, status, created_at, updated_at)
SELECT p.id, 'Jasa Servis AC', 'Servis AC', 'repair', 'Jl. Setiabudi No. 112, Gegerkalong', 'Banyak warga membutuhkan teknisi AC langganan yang cepat datang, terutama saat musim panas. Saat ini warga harus menghubungi teknisi dari luar kelurahan.', 'Gegerkalong, Bandung', -6.8875, 107.5978, 1.4, 19, 1, 'aktif', datetime('now', '-5 hours'), datetime('now', '-5 hours')
FROM (SELECT id FROM profiles WHERE username = 'warga_sukajadi' LIMIT 1) AS p
WHERE NOT EXISTS (SELECT 1 FROM needs WHERE needs.title = 'Jasa Servis AC');

INSERT INTO needs (profile_id, title, marker_label, category, location, description, area, latitude, longitude, distance_km, supporters, opportunity, status, created_at, updated_at)
SELECT p.id, 'Bibit Tanaman', 'Bibit Tanaman', 'retail', 'Jl. Surya Sumantri No. 45, Sukawarna', 'Warga ingin menanam sayur di pekarangan, tetapi belum ada penjual bibit tanaman terdekat. Toko bibit keliling atau kios kecil akan membantu kebutuhan ini.', 'Sukawarna, Bandung', -6.8943, 107.5979, 2.1, 15, 1, 'aktif', datetime('now', '-26 hours'), datetime('now', '-26 hours')
FROM (SELECT id FROM profiles WHERE username = 'warga_sukajadi' LIMIT 1) AS p
WHERE NOT EXISTS (SELECT 1 FROM needs WHERE needs.title = 'Bibit Tanaman');

INSERT INTO needs (profile_id, title, marker_label, category, location, description, area, latitude, longitude, distance_km, supporters, opportunity, status, created_at, updated_at)
SELECT p.id, 'Jasa Fotokopi', 'Fotokopi', 'retail', 'Jl. Pasteur No. 8, Pasteur', 'Pelajar dan pekerja sekitar kesulitan mencetak serta memfotokopi dokumen. Layanan fotokopi dekat permukiman akan menghemat waktu warga.', 'Pasteur, Bandung', -6.8912, 107.5908, 1.7, 12, 1, 'aktif', datetime('now', '-9 hours'), datetime('now', '-9 hours')
FROM (SELECT id FROM profiles WHERE username = 'warga_sukajadi' LIMIT 1) AS p
WHERE NOT EXISTS (SELECT 1 FROM needs WHERE needs.title = 'Jasa Fotokopi');

INSERT INTO needs (profile_id, title, marker_label, category, location, description, area, latitude, longitude, distance_km, supporters, opportunity, status, created_at, updated_at)
SELECT p.id, 'Warung Sayur Segar', 'Warung Sayur', 'culinary', 'Jl. Sukajadi No. 76, Sukajadi', 'Warga lansia membutuhkan warung sayur yang dekat dan menyediakan kebutuhan masak harian. Saat ini mereka harus berjalan jauh atau menunggu pedagang keliling.', 'Sukajadi, Bandung', -6.8936, 107.5924, 1.1, 31, 1, 'aktif', datetime('now', '-74 hours'), datetime('now', '-74 hours')
FROM (SELECT id FROM profiles WHERE username = 'warga_sukajadi' LIMIT 1) AS p
WHERE NOT EXISTS (SELECT 1 FROM needs WHERE needs.title = 'Warung Sayur Segar');

INSERT INTO needs (profile_id, title, marker_label, category, location, description, area, latitude, longitude, distance_km, supporters, opportunity, status, created_at, updated_at)
SELECT p.id, 'Klinik 24 Jam', 'Klinik 24 Jam', 'health', 'Jl. Setiabudi No. 210, Gegerkalong', 'Belum ada klinik yang buka 24 jam di sekitar permukiman. Warga membutuhkan akses layanan kesehatan cepat, terutama pada malam hari dan akhir pekan.', 'Gegerkalong, Bandung', -6.8868, 107.591, 1.9, 42, 1, 'aktif', datetime('now', '-8 hours'), datetime('now', '-8 hours')
FROM (SELECT id FROM profiles WHERE username = 'warga_sukajadi' LIMIT 1) AS p
WHERE NOT EXISTS (SELECT 1 FROM needs WHERE needs.title = 'Klinik 24 Jam');
