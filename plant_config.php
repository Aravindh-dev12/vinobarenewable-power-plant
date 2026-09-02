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
    $id = strtolower(trim((string)$plantId));
    if ($id === '') return '';

    $aliases = [
        'vinoba-1' => 'vinoba-1',
        'vinoba-velliyanai' => 'vinoba-1',
        'vinoba' => 'vinoba-1',
        'ssv' => 'ssv',
        'makkalpower' => 'ssv',
        'makkal-power' => 'ssv',
        'anushyam' => 'ssv',
    ];
    return $aliases[$id] ?? $id;
}

function plant_info(?string $plantId): ?array
{
    $id = normalize_plant_id($plantId);
    $catalog = plant_catalog();
    return $catalog[$id] ?? null;
}

function is_valid_plant_id(?string $plantId): bool
{
    $id = normalize_plant_id($plantId);
    return isset(plant_catalog()[$id]);
}

function migrate_user_plant_alias(mysqli $conn, array &$user): void
{
    $old = trim((string)($user['plant_id'] ?? ''));
    if ($old === '') return;
    $new = normalize_plant_id($old);
    if (!is_valid_plant_id($new) || $new === $old) {
        if (is_valid_plant_id($new)) $user['plant_id'] = $new;
        return;
    }

    $stmt = $conn->prepare('UPDATE users SET plant_id = ? WHERE id = ?');
    if ($stmt) {
        $uid = (int)$user['id'];
        $stmt->bind_param('si', $new, $uid);
        $stmt->execute();
        $stmt->close();
    }
    $user['plant_id'] = $new;
}
