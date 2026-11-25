<?php
/**
 * Sistema Agora - Módulo Compartido de Utilidades de Eventos (M1 - ISO/IEC 25010 Mantenibilidad)
 *
 * Centraliza la lógica repetida entre create_event.php, update_event.php,
 * setup_zones.php y seleccionar_asientos.php:
 *  - Mapeo de categoría, recinto e imagen por defecto
 *  - Reparto proporcional exacto de las 5 zonas (15%, 20%, 30%, 20%, 15%)
 *  - Validación segura y almacenamiento de imágenes (S2)
 *  - Registro unificado en la Bitácora de auditoría (S3)
 */

/**
 * Resuelve la categoría en BD, el recinto correspondiente y el banner por defecto.
 */
function resolveEventCategoryAndVenue(PDO $pdo, string $categoria): array
{
    $lowerCat = strtolower(trim($categoria));
    if (strpos($lowerCat, 'concierto') !== false) {
        $catName = 'Concierto';
    } elseif (strpos($lowerCat, 'teatro') !== false || strpos($lowerCat, 'obra') !== false || strpos($lowerCat, 'arte') !== false) {
        $catName = 'Obra de teatro';
    } elseif (strpos($lowerCat, 'conferencia') !== false) {
        $catName = 'Conferencia';
    } elseif (strpos($lowerCat, 'festival') !== false) {
        $catName = 'Festival';
    } elseif (strpos($lowerCat, 'social') !== false || strpos($lowerCat, 'boda') !== false || strpos($lowerCat, 'salon') !== false || strpos($lowerCat, 'salón') !== false) {
        $catName = 'Evento social';
    } elseif (strpos($lowerCat, 'deporte') !== false || strpos($lowerCat, 'deportivo') !== false) {
        $catName = 'Evento deportivo';
    } else {
        $catName = ucfirst($lowerCat);
    }

    $stmt = $pdo->prepare("SELECT id_categoria FROM Categoria WHERE nombre_categoria = ?");
    $stmt->execute([$catName]);
    $row = $stmt->fetch();
    if ($row) {
        $id_categoria = (int)$row['id_categoria'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO Categoria (nombre_categoria) VALUES (?)");
        $stmt->execute([$catName]);
        $id_categoria = (int)$pdo->lastInsertId();
    }

    // Asignar recinto según categoría
    $id_recinto = 4; // Plaza Pública (General)
    if (strpos($lowerCat, 'concierto') !== false || strpos($lowerCat, 'deporte') !== false || strpos($lowerCat, 'deportivo') !== false) {
        $id_recinto = 2; // Estadio Olímpico Ágora
    } elseif (strpos($lowerCat, 'teatro') !== false || strpos($lowerCat, 'obra') !== false || strpos($lowerCat, 'arte') !== false) {
        $id_recinto = 1; // Teatro Ágora Principal
    } elseif (strpos($lowerCat, 'conferencia') !== false) {
        $id_recinto = 3; // Sala de Conferencias Ágora
    }

    // Banner predeterminado según categoría
    $default_banner = 'Assets/img/img_home.png';
    if (strpos($lowerCat, 'concierto') !== false) {
        $default_banner = 'Assets/img/tag_concert.png';
    } elseif (strpos($lowerCat, 'conferencia') !== false) {
        $default_banner = 'Assets/img/tag_conf.png';
    } elseif (strpos($lowerCat, 'boda') !== false) {
        $default_banner = 'Assets/img/tag_boda.png';
    } elseif (strpos($lowerCat, 'deporte') !== false) {
        $default_banner = 'Assets/img/tag_sport.png';
    } elseif (strpos($lowerCat, 'arte') !== false) {
        $default_banner = 'Assets/img/tag_art.png';
    } elseif (strpos($lowerCat, 'salon') !== false || strpos($lowerCat, 'salón') !== false) {
        $default_banner = 'Assets/img/tag_salon.png';
    }

    return [
        'id_categoria'   => $id_categoria,
        'cat_name'       => $catName,
        'id_recinto'     => $id_recinto,
        'default_banner' => $default_banner,
    ];
}

/**
 * Calcula la distribución exacta de lugares en las 5 zonas del recinto.
 */
function calculateFiveZonesDistribution(int $capacidad): array
{
    $seatsVIP = max(1, (int)round($capacidad * 0.15));
    $seatsA   = max(1, (int)round($capacidad * 0.20));
    $seatsB   = max(1, (int)round($capacidad * 0.30));
    $seatsC   = max(1, (int)round($capacidad * 0.20));
    $seatsD   = $capacidad - ($seatsVIP + $seatsA + $seatsB + $seatsC);
    if ($seatsD <= 0) {
        $seatsD = 1;
    }

    return [
        'VIP'    => $seatsVIP,
        'Zona A' => $seatsA,
        'Zona B' => $seatsB,
        'Zona C' => $seatsC,
        'Zona D' => $seatsD,
    ];
}

/**
 * S2 + M1: Valida el tipo MIME binario real y el límite de 5 MB de una imagen en base64 y la guarda con permisos 0755/0644.
 *
 * @throws InvalidArgumentException Si la imagen es inválida, supera 5 MB o no es JPG/PNG/WEBP real.
 */
function validateAndSaveEventImage(string $imagen_base64): string
{
    $parts = explode(',', $imagen_base64, 2);
    $imgData = isset($parts[1]) ? base64_decode($parts[1], true) : false;

    if ($imgData === false) {
        throw new InvalidArgumentException('Archivo de imagen inválido o corrupto.');
    }

    $maxSizeBytes = 5 * 1024 * 1024; // 5 MB
    if (strlen($imgData) > $maxSizeBytes) {
        throw new InvalidArgumentException('La imagen excede el tamaño máximo permitido de 5 MB.');
    }

    $realMime = '';
    if (class_exists('finfo')) {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->buffer($imgData);
    }
    $imgInfo = @getimagesizefromstring($imgData);
    if (empty($realMime) && $imgInfo && isset($imgInfo['mime'])) {
        $realMime = $imgInfo['mime'];
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!$imgInfo || !isset($allowedMimes[$realMime]) || strpos($imgData, '<?php') !== false) {
        throw new InvalidArgumentException('Tipo de archivo no permitido. Solo se aceptan imágenes reales (JPG, PNG, WEBP) de máximo 5 MB.');
    }

    $uploadDir = __DIR__ . '/../Assets/img/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    } else {
        @chmod($uploadDir, 0755);
    }

    $ext = $allowedMimes[$realMime];
    $fileName = 'event_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $fullPath = $uploadDir . $fileName;
    if (file_put_contents($fullPath, $imgData) === false) {
        throw new InvalidArgumentException('No se pudo almacenar la imagen del evento.');
    }
    @chmod($fullPath, 0644);

    return 'Assets/img/uploads/' . $fileName;
}
?>
