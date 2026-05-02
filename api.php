<?php
require_once 'config.php';

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === ALLOWED_ORIGIN || strpos($origin, 'cheesmart.ru') !== false || $origin === '') {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
} else {
    header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = $_GET['action'] ?? '';

// ——— Вспомогательная функция запроса к МойСклад ———
function ms_request(string $endpoint, string $method = 'GET', array $body = null): array {
    $url = MS_API_BASE . $endpoint;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . MS_TOKEN,
            'Content-Type: application/json',
            'Accept-Encoding: gzip',
        ],
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => 'cURL error: ' . $error, 'code' => 0];
    }
    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        return ['error' => $data['errors'][0]['error'] ?? 'Ошибка API', 'code' => $httpCode];
    }
    return $data ?? [];
}

// ——— ACTION: получить прайс-листы ———
if ($action === 'pricelists') {
    $data = ms_request('/entity/pricelist?limit=100');
    if (isset($data['error'])) { http_response_code(500); echo json_encode($data); exit; }

    $lists = array_map(fn($p) => [
        'id'   => $p['id'],
        'name' => $p['name'],
    ], $data['rows'] ?? []);

    echo json_encode(['pricelists' => $lists]);
    exit;
}

// ——— ACTION: получить товары из прайс-листа ———
if ($action === 'products') {
    $pricelist_id = MS_PRICELIST_ID;
    if (!$pricelist_id) {
        http_response_code(400);
        echo json_encode(['error' => 'MS_PRICELIST_ID не задан в config.php']);
        exit;
    }

    // Получаем прайс-лист с позициями
    $pl = ms_request("/entity/pricelist/{$pricelist_id}?expand=rows.assortment");
    if (isset($pl['error'])) { http_response_code(500); echo json_encode($pl); exit; }

    $products = [];
    foreach ($pl['rows'] ?? [] as $row) {
        $item = $row['assortment'] ?? [];
        if (!$item) continue;

        // Берём оптовую цену из row (тип цены указан в прайс-листе)
        $price = null;
        foreach ($row['prices'] ?? [] as $p) {
            $price = round($p['value'] / 100, 2); // МойСклад хранит в копейках
            break;
        }

        // Если цена 0 или null — пропускаем
        if (!$price) continue;

        $type = $item['meta']['type'] ?? '';
        $name = $item['name'] ?? '';

        // Для модификаций добавляем характеристику
        if ($type === 'variant') {
            $chars = array_map(fn($c) => $c['value'], $item['characteristics'] ?? []);
            if ($chars) $name .= ' (' . implode(', ', $chars) . ')';
        }

        // Артикул
        $article = $item['article'] ?? $item['code'] ?? '';

        // Единица измерения
        $uom = $item['uom']['name'] ?? 'шт';

        // Описание
        $description = $item['description'] ?? '';

        // Изображение (первое)
        $image = null;
        if (!empty($item['images']['rows'][0]['miniature']['href'])) {
            $image = $item['images']['rows'][0]['miniature']['href'];
        }

        $products[] = [
            'id'          => $item['id'],
            'name'        => $name,
            'article'     => $article,
            'price'       => $price,
            'uom'         => $uom,
            'description' => $description,
            'image'       => $image,
            'type'        => $type,
        ];
    }

    // Сортировка по имени
    usort($products, fn($a, $b) => strcmp($a['name'], $b['name']));

    echo json_encode([
        'name'     => $pl['name'] ?? 'Прайс-лист',
        'products' => $products,
        'count'    => count($products),
    ]);
    exit;
}

// ——— ACTION: создать заказ ———
if ($action === 'order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $customerName  = trim($input['name'] ?? '');
    $customerPhone = trim($input['phone'] ?? '');
    $customerEmail = trim($input['email'] ?? '');
    $comment       = trim($input['comment'] ?? '');
    $items         = $input['items'] ?? [];

    // Базовая валидация
    if (!$customerName || !$customerPhone || empty($items)) {
        http_response_code(400);
        echo json_encode(['error' => 'Заполните имя, телефон и добавьте товары']);
        exit;
    }

    if (!MS_ORGANIZATION_ID) {
        http_response_code(500);
        echo json_encode(['error' => 'MS_ORGANIZATION_ID не задан в config.php']);
        exit;
    }

    // Ищем или создаём контрагента по телефону
    $phone_clean = preg_replace('/\D/', '', $customerPhone);
    $counterparty_id = null;

    $search = ms_request('/entity/counterparty?filter=phone=' . urlencode($customerPhone) . '&limit=1');
    if (!empty($search['rows'][0]['id'])) {
        $counterparty_id = $search['rows'][0]['id'];
    } else {
        // Создаём нового контрагента
        $cp = ms_request('/entity/counterparty', 'POST', [
            'name'  => $customerName,
            'phone' => $customerPhone,
            'email' => $customerEmail ?: null,
            'companyType' => 'individual',
        ]);
        if (isset($cp['error'])) {
            http_response_code(500);
            echo json_encode(['error' => 'Не удалось создать контрагента: ' . $cp['error']]);
            exit;
        }
        $counterparty_id = $cp['id'];
    }

    // Формируем позиции заказа
    $positions = [];
    foreach ($items as $item) {
        if (empty($item['id']) || empty($item['quantity'])) continue;
        $qty = max(1, (int)$item['quantity']);
        $positions[] = [
            'quantity' => $qty,
            'price'    => round(($item['price'] ?? 0) * 100), // в копейки
            'assortment' => [
                'meta' => [
                    'href' => MS_API_BASE . '/entity/' . ($item['type'] === 'variant' ? 'variant' : 'product') . '/' . $item['id'],
                    'type' => $item['type'] === 'variant' ? 'variant' : 'product',
                    'mediaType' => 'application/json',
                ]
            ],
        ];
    }

    // Создаём заказ покупателя
    $fullComment = $customerName . ', ' . $customerPhone;
    if ($customerEmail) $fullComment .= ', ' . $customerEmail;
    if ($comment) $fullComment .= "\n" . $comment;

    $order = ms_request('/entity/customerorder', 'POST', [
        'organization' => [
            'meta' => [
                'href' => MS_API_BASE . '/entity/organization/' . MS_ORGANIZATION_ID,
                'type' => 'organization',
                'mediaType' => 'application/json',
            ]
        ],
        'agent' => [
            'meta' => [
                'href' => MS_API_BASE . '/entity/counterparty/' . $counterparty_id,
                'type' => 'counterparty',
                'mediaType' => 'application/json',
            ]
        ],
        'description' => $fullComment,
        'positions' => $positions,
    ]);

    if (isset($order['error'])) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка создания заказа: ' . $order['error']]);
        exit;
    }

    // Email уведомление (если настроен)
    if (NOTIFY_EMAIL) {
        $subject = "Новый заказ с прайс-листа — {$customerName}";
        $body = "Имя: {$customerName}\nТел: {$customerPhone}\nEmail: {$customerEmail}\n\n";
        foreach ($items as $item) {
            $body .= "• {$item['name']} × {$item['quantity']} шт — {$item['price']} руб\n";
        }
        if ($comment) $body .= "\nКомментарий: {$comment}";
        mail(NOTIFY_EMAIL, $subject, $body, 'From: noreply@cheesmart.ru');
    }

    echo json_encode([
        'success' => true,
        'orderId' => $order['id'],
        'orderName' => $order['name'] ?? '',
    ]);
    exit;
}

// Неизвестный action
http_response_code(404);
echo json_encode(['error' => 'Неизвестное действие']);
