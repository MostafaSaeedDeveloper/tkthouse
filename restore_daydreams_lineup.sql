-- Restore Daydreams (event_id = 27) lineup artists
-- Run this on your production database

-- Step 1: Delete current lineups for event 27
DELETE FROM `event_lineups` WHERE `event_id` = 27;

-- Step 2: Re-insert the original artists
INSERT INTO `event_lineups` (`id`, `event_id`, `artist_name`, `instagram`, `performance_time`, `performance_date`, `image`, `sort_order`, `created_at`, `updated_at`) VALUES
(53, 27, 'Paradoks',           'paradoks.music',       '01:00 am', '2026-06-12', 'uploads/events/lineup/YGOEUhRUY2omHe487GQFw5Cs85nwa1tMSqifm0NR.jpg', 1, '2026-06-01 13:56:27', '2026-06-01 13:56:27'),
(54, 27, 'TH;EN',              'thenmusic',            '03:00 PM',  '2026-06-12', 'uploads/events/lineup/uUVrFA2KhOajHEI9sGxzBE8Nl9Gah1d1p1U2Ytxu.jpg', 2, '2026-06-01 13:56:27', '2026-06-01 13:56:27'),
(55, 27, 'AIRSAND & Turaniqa', 'airsand_music',        '04:30 PM',  '2026-06-12', 'uploads/events/lineup/TN5CAnZ00o8KtxisSGHQjg7ipoE772m7TlOvYuBQ.jpg', 4, '2026-06-01 13:56:27', '2026-06-01 13:56:27'),
(56, 27, 'Andrewboy',          'andrewboymuzik',       '06:00 PM',  '2026-06-12', 'uploads/events/lineup/CojusglnQC4cAITehdV8KVIdnEHJ2AL3AxynoWXk.jpg', 5, '2026-06-01 13:56:27', '2026-06-01 13:56:27'),
(57, 27, 'Baris Bayrak',       'barisbayrak_ofc',      '08:00 PM',  '2026-06-12', 'uploads/events/lineup/7Ql5v9U6rQ0mWSG2vl0yDIKOZEdIFNx0pCwfp42T.jpg', 6, '2026-06-01 13:56:27', '2026-06-01 13:56:27'),
(58, 27, 'RIKAYA',             'rikayamusic',          '10:00 PM',  '2026-06-13', 'uploads/events/lineup/QGNtPPsLKTnhIH2qxfQbvjJ6iSlUe2CikOb9phHu.jpg', 7, '2026-06-01 13:56:27', '2026-06-01 13:56:27'),
(59, 27, 'Final Request',      'finalrequest_music',   '01:00 AM',  '2026-06-13', 'uploads/events/lineup/XwknAdSaF4u6iuWtHXEt6R4hKaz7pLgBIdzwxYaW.jpg', 8, '2026-06-01 13:56:27', '2026-06-01 13:56:27'),
(60, 27, 'S.A.B.R.I',         'sabrimusic_org',       '10:00 PM',  '2026-06-13', 'uploads/events/lineup/Ee4AO2KdpdoXgAejb1zx8qa3Hc17K9tf7arWghya.jpg', 9, '2026-06-01 13:56:27', '2026-06-01 13:56:27');
