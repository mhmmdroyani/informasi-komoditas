<?php

require_once __DIR__ . '/volume_units.php';

function normalize_commodity_group_name($name): string
{
    $name = trim(preg_replace('/\s+/', ' ', (string)$name));
    if (preg_match('/^sarang\s+burung\s+walet(?:\b|$)/i', $name)) {
        return $name;
    }

    foreach (['Burung', 'Ular', 'Vaksin', 'Ayam'] as $groupName) {
        if (preg_match('/^' . preg_quote($groupName, '/') . '\b/i', $name)) {
            return $groupName;
        }
    }

    return $name;
}

function aggregate_commodity_rows(array $rows): array
{
    $groups = [];
    foreach ($rows as $row) {
        $normalizedVolume = normalize_compatible_volume_unit($row['volume'] ?? 0, $row['satuan'] ?? '');
        $commodity = normalize_commodity_group_name($row['nama_komoditas'] ?? '');
        $commodityKey = strtolower(preg_replace('/\s+/', ' ', $commodity));
        $unitKey = strtolower(preg_replace('/\s+/', ' ', $normalizedVolume['unit']));
        $groupKey = $commodityKey . "\0" . $unitKey;

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'klasifikasi' => (string)($row['klasifikasi'] ?? ''),
                'nama_komoditas' => $commodity,
                'nama_komoditas_lokal' => (string)($row['nama_komoditas_lokal'] ?? ''),
                'frekuensi' => 0,
                'volume' => 0.0,
                'satuan' => $normalizedVolume['unit'],
                'nilai_ekspor' => 0.0,
                'daerah_asal' => [],
                'daerah_tujuan' => [],
                'negara_tujuan' => [],
                'periode' => (string)($row['periode'] ?? ''),
                'tanggal_mulai' => $row['tanggal_mulai'] ?? null,
                'tanggal_selesai' => $row['tanggal_selesai'] ?? null,
                '_selection_key' => hash('sha256', $groupKey),
                '_source_ids' => [],
                '_source_names' => [],
                'source_count' => 0,
                'published_count' => 0,
            ];
        }

        $group =& $groups[$groupKey];
        $group['frekuensi'] += (int)($row['frekuensi'] ?? 0);
        $group['volume'] += $normalizedVolume['volume'];
        $group['nilai_ekspor'] += (float)($row['nilai_ekspor'] ?? 0);
        if (isset($row['id'])) {
            $group['_source_ids'][] = (int)$row['id'];
        }
        $sourceName = trim(preg_replace('/\s+/', ' ', (string)($row['nama_komoditas'] ?? '')));
        $group['_source_names'][strtolower($sourceName)] = true;
        $group['source_count']++;
        $group['published_count'] += (int)($row['is_published'] ?? 0) === 1 ? 1 : 0;
        if ($group['klasifikasi'] === '') {
            $group['klasifikasi'] = (string)($row['klasifikasi'] ?? '');
        }
        if ($group['nama_komoditas_lokal'] === '' && !empty($row['nama_komoditas_lokal'])) {
            $group['nama_komoditas_lokal'] = (string)$row['nama_komoditas_lokal'];
        }
        foreach (['daerah_asal', 'daerah_tujuan', 'negara_tujuan'] as $field) {
            $parts = preg_split('/\s*(?:,|;|\/|\|)\s*/', (string)($row[$field] ?? ''));
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $group[$field][strtolower($part)] = $part;
                }
            }
        }
        unset($group);
    }

    $aggregated = [];
    foreach ($groups as $group) {
        if ($group['source_count'] > 1) {
            $group['tanggal_mulai'] = null;
            $group['tanggal_selesai'] = null;
        }
        if (count($group['_source_names']) > 1) {
            $group['nama_komoditas_lokal'] = '';
        }
        foreach (['daerah_asal', 'daerah_tujuan', 'negara_tujuan'] as $field) {
            $group[$field] = implode(', ', array_values($group[$field]));
        }
        $group['publication_status'] = $group['published_count'] === 0
            ? 'Draft'
            : ($group['published_count'] === $group['source_count'] ? 'Published' : 'Campuran');
        $group['is_published'] = $group['published_count'] === $group['source_count'] ? 1 : 0;
        unset($group['published_count']);
        unset($group['_source_names']);
        $aggregated[] = $group;
    }

    return $aggregated;
}
