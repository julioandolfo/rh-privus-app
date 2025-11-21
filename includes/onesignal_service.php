<?php
/**
 * Serviço centralizado para envio de notificações via OneSignal
 */

require_once __DIR__ . '/functions.php';

if (!function_exists('onesignal_send_notification')) {
    /**
     * @param array $input dados da notificação
     * @param callable|null $logger função para registrar logs
     * @return array
     * @throws Exception
     */
    function onesignal_send_notification(array $input, ?callable $logger = null)
    {
        $log = function ($message) use ($logger) {
            if (is_callable($logger)) {
                call_user_func($logger, $message);
            } else {
                error_log($message);
            }
        };

        $pdo = getDB();

        $usuario_id = isset($input['usuario_id']) ? intval($input['usuario_id']) : null;
        $colaborador_id = isset($input['colaborador_id']) ? intval($input['colaborador_id']) : null;
        $broadcast = !empty($input['broadcast']);
        $titulo = trim($input['titulo'] ?? 'Notificação');
        $mensagem = trim($input['mensagem'] ?? '');
        $url = $input['url'] ?? '';
        $icone = $input['icone'] ?? '';

        $log("OneSignal Service - Dados recebidos: " . json_encode([
            'usuario_id' => $usuario_id,
            'colaborador_id' => $colaborador_id,
            'broadcast' => $broadcast,
            'titulo' => $titulo,
        ]));

        if ($mensagem === '') {
            throw new Exception('Mensagem é obrigatória');
        }

        if (!$usuario_id && !$colaborador_id && !$broadcast) {
            throw new Exception('Informe usuario_id, colaborador_id ou broadcast');
        }

        $stmt = $pdo->query("SELECT app_id, rest_api_key FROM onesignal_config ORDER BY id DESC LIMIT 1");
        $config = $stmt->fetch();

        if (!$config || empty($config['app_id']) || empty($config['rest_api_key'])) {
            throw new Exception('OneSignal não configurado');
        }

        if ($colaborador_id) {
            $stmt = $pdo->prepare("SELECT player_id FROM onesignal_subscriptions WHERE colaborador_id = ?");
            $stmt->execute([$colaborador_id]);
        } elseif ($usuario_id) {
            $stmt = $pdo->prepare("SELECT player_id FROM onesignal_subscriptions WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
        } else {
            $stmt = $pdo->query("SELECT player_id FROM onesignal_subscriptions");
        }

        $subscriptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($subscriptions)) {
            throw new Exception('Nenhuma subscription encontrada para o destinatário');
        }

        $baseUrl = get_base_url();
        if (empty($url)) {
            $url = $baseUrl . '/pages/dashboard.php';
        }
        if (strpos($url, 'http') !== 0) {
            $url = $baseUrl . '/' . ltrim($url, '/');
        }

        if (!empty($icone)) {
            if (strpos($icone, 'http') !== 0) {
                $icone = $baseUrl . '/' . ltrim($icone, '/');
            }
        } else {
            $icone = $baseUrl . '/assets/media/logos/favicon.png';
        }

        $badge = $baseUrl . '/assets/media/logos/favicon.png';

        $payload = [
            'app_id' => $config['app_id'],
            'include_player_ids' => $subscriptions,
            'headings' => [
                'pt' => $titulo,
                'en' => $titulo,
            ],
            'contents' => [
                'pt' => $mensagem,
                'en' => $mensagem,
            ],
            'url' => $url,
            'chrome_web_icon' => $icone,
            'chrome_web_badge' => $badge,
        ];

        $log('OneSignal Service - Iniciando requisição para OneSignal com ' . count($subscriptions) . ' player(s)');

        $ch = curl_init('https://onesignal.com/api/v1/notifications');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . $config['rest_api_key'],
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $elapsedTime = microtime(true) - $startTime;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $log('OneSignal Service - Resposta recebida em ' . round($elapsedTime, 2) . ' segundos (HTTP ' . $httpCode . ')');

        if ($curlError) {
            $log('OneSignal Service - cURL Error: ' . $curlError);
            if (strpos($curlError, 'timeout') !== false || strpos($curlError, 'timed out') !== false) {
                throw new Exception('A requisição ao OneSignal demorou muito para responder. Tente novamente em instantes.');
            }
            throw new Exception('Erro ao comunicar com OneSignal: ' . $curlError);
        }

        if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Erro ao decodificar resposta do OneSignal: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'enviadas' => count($subscriptions),
                'onesignal_id' => $result['id'] ?? null,
                'response' => $result,
            ];
        }

        $error = json_decode($response, true);
        $errorMessage = 'Erro ao enviar notificação';
        if (isset($error['errors']) && is_array($error['errors']) && !empty($error['errors'])) {
            $first = $error['errors'][0];
            $errorMessage = is_array($first) ? ($first['message'] ?? $errorMessage) : $first;
        } elseif (isset($error['message'])) {
            $errorMessage = $error['message'];
        }

        $log('OneSignal Service - Erro: ' . $errorMessage . ' (HTTP ' . $httpCode . ')');
        throw new Exception($errorMessage . ' (HTTP ' . $httpCode . ')');
    }
}

