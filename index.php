<?php
// Настройки
$url = '';

// Заголовки для обхода защиты
$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.9',
    'Accept-Encoding: gzip, deflate, br',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1',
    'Sec-Fetch-Dest: document',
    'Sec-Fetch-Mode: navigate',
    'Sec-Fetch-Site: none',
];

// Инициализация cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_ENCODING => 'gzip',
    CURLOPT_COOKIEFILE => '',
    CURLOPT_REFERER => 'https://www.google.com/',
]);

// Получение HTML
$html = curl_exec($ch);

curl_close($ch);

// Создание DOM документа
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// Селекторы для поиска товаров
$productSelectors = [
    '//div[contains(@class, "product")]',
    '//li[contains(@class, "product")]',
    '//div[contains(@class, "product-item")]',
    '//article[contains(@class, "product")]',
    '//div[contains(@class, "product-card")]',
    '//div[contains(@class, "item") and contains(@class, "product")]',
];

$products = null;
foreach ($productSelectors as $selector) {
    $products = $xpath->query($selector);
    if ($products->length > 0) {
        break;
    }
}

// Если товары не найдены стандартными селекторами, ищем по структуре
if (!$products || $products->length === 0) {
    // Ищем контейнеры, которые могут содержать товары
    $products = $xpath->query('//div[.//*[contains(text(), "$")]]');
}

$results = [];

if ($products && $products->length > 0) {
    foreach ($products as $product) {
        // Извлечение названия товара
        $name = '';
        $nameSelectors = [
            './/h1',
            './/h2',
            './/h3',
            './/a[contains(@class, "title")]',
            './/span[contains(@class, "name")]',
            './/div[contains(@class, "title")]',
            './/div[contains(@class, "name")]',
            './/*[contains(@class, "product-name")]',
            './/*[@itemprop="name"]',
            './/*[contains(@class, "description")]'
        ];

        foreach ($nameSelectors as $selector) {
            $nameNode = $xpath->query($selector, $product)->item(0);
            if ($nameNode) {
                $name = trim($nameNode->nodeValue);
                if (!empty($name) && strlen($name) > 10) break;
            }
        }

        // Пропускаем если название слишком короткое или содержит служебные слова
        if (
            empty($name) || strlen($name) < 10 ||
            stripos($name, 'filter') !== false ||
            stripos($name, 'sort') !== false ||
            stripos($name, 'search') !== false ||
            stripos($name, 'items per page') !== false
        ) {
            continue;
        }

        // Извлечение цены
        $price = '';
        $priceSelectors = [
            './/span[contains(@class, "price")]',
            './/div[contains(@class, "price")]',
            './/span[contains(@class, "cost")]',
            './/*[@itemprop="price"]',
            './/*[contains(@class, "currency")]',
            './/*[contains(@class, "amount")]'
        ];

        foreach ($priceSelectors as $selector) {
            $priceNode = $xpath->query($selector, $product)->item(0);
            if ($priceNode) {
                $priceText = trim($priceNode->nodeValue);
                // Извлекаем числа из цены (включая десятичные разделители)
                if (preg_match('/\d+[.,]?\d*/', $priceText, $matches)) {
                    $price = $matches[0];
                    // Форматируем цену
                    $price = preg_replace('/[^\d.]/', '', $price);
                    break;
                }
            }
        }

        // Извлечение ссылки
        $link = '';
        $linkNode = $xpath->query('.//a/@href', $product)->item(0);
        if ($linkNode) {
            $link = $linkNode->nodeValue;
            // Создание абсолютного URL
            if ($link && $link[0] === '/') {
                $parsedUrl = parse_url($url);
                $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                $link = $baseUrl . $link;
            } elseif ($link && !preg_match('/^https?:\/\//', $link)) {
                $link = dirname($url) . '/' . ltrim($link, '/');
            }
        }

        // Добавляем в результаты
        if (!empty($name)) {
            $results[] = [
                'name' => htmlspecialchars($name),
                'price' => $price ? '$' . htmlspecialchars($price) : 'Нет цены',
                'link' => $link ? htmlspecialchars($link) : '#'
            ];
        }
    }
}

// Вывод результатов
?>
<!DOCTYPE html>
<html>

<head>
    <title>Товары</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 20px 0;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        a {
            color: #0066cc;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .info {
            background: #f0f8ff;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <h1>Товары</h1>

    <div class="info">
        <strong>Информация:</strong> Найдено товаров: <?= count($results) ?>
    </div>

    <?php if (count($results) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Название товара</th>
                    <th>Цена</th>
                    <th>Ссылка</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $i => $item): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= $item['name'] ?></td>
                        <td><?= $item['price'] ?></td>
                        <td>
                            <?php if ($item['link'] !== '#'): ?>
                                <a href="<?= $item['link'] ?>" target="_blank" rel="noopener">Перейти</a>
                            <?php else: ?>
                                Нет ссылки
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Товары не найдены.</p>
    <?php endif; ?>
</body>

</html>