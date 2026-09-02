<?php
/** Canonical plant identity configuration. */
function plant_catalog(): array
{
    return [
        'vinoba-1' => [
            'id' => 'vinoba-1',
            'name' => 'Vinoba Renewable Energy Private Limited',
            'service_number' => '06914430133',
            'capacity' => 2.0,
            'location' => 'Karur',
        ],
        'ssv' => [
            'id' => 'ssv',
            'name' => 'SSV Green Power Private Limited',
            'service_number' => '06914430134',
            'capacity' => 2.0,
            'location' => 'Karur',
        ],
    ];
}

function normalize_plant_id(?string $plantId): string
{
    return strtolower(trim((string)$plantId));
}

function plant_info(?string $plantId): ?array
{
    $id = normalize_plant_id($plantId);
    $catalog = plant_catalog();
    return $catalog[$id] ?? null;
}

function is_valid_plant_id(?string $plantId): bool
{
    return isset(plant_catalog()[normalize_plant_id($plantId)]);
}

function migrate_user_plant_alias(mysqli $conn, array &$user): void
{
    $raw = (string)($user['plant_id'] ?? '');
    $normalized = normalize_plant_id($raw);
    if (!is_valid_plant_id($normalized)) return;

    $user['plant_id'] = $normalized;
    if ($normalized === $raw) return;

    $stmt = $conn->prepare('UPDATE users SET plant_id = ? WHERE id = ?');
    if ($stmt) {
        $uid = (int)$user['id'];
        $stmt->bind_param('si', $normalized, $uid);
        $stmt->execute();
        $stmt->close();
    }
}
