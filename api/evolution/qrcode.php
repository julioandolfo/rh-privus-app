<?php
/**
 * API - QR Code e Status de Conexão da Evolution API
 * Endpoints usados pelo painel de configurações (AJAX)
 */

header('Content-Type: application/json');
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/evolution_service.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Apenas ADMIN
if (!in_array($_SESSION['usuario']['role'] ?? '', ['ADMIN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';
$config = evolution_get_config();

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Evolution API não configurada. Salve as configurações primeiro.']);
    exit;
}

switch ($action) {

    // ─── Status de conexão ────────────────────────────────────────────────────
    case 'status':
        $result = evolution_verificar_conexao($config);
        echo json_encode([
            'success'   => true,
            'connected' => $result['connected'] ?? false,
            'state'     => $result['state']     ?? 'unknown',
            'data'      => $result['data']       ?? null,
            'raw'       => $result['raw']        ?? null,
            'url'       => $result['url']        ?? null,
            'http_code' => $result['http_code']  ?? null,
            'error'     => $result['error']      ?? null,
        ]);
        break;

    // ─── Gerar / buscar QR Code ───────────────────────────────────────────────
    case 'qrcode':
        $instance = $config['instance_name'];

        // Primeiro verifica se já está conectado
        $status = evolution_verificar_conexao($config);
        if ($status['connected']) {
            echo json_encode([
                'success'   => true,
                'connected' => true,
                'state'     => 'open',
                'message'   => 'WhatsApp já está conectado!',
            ]);
            break;
        }

        // Tenta criar a instância (ignorado silenciosamente se já existir)
        evolution_request('POST', 'instance/create', [
            'instanceName' => $instance,
            'qrcode'       => true,
            'integration'  => 'WHATSAPP-BAILEYS',
        ], $config);

        // Busca QR Code do endpoint /instance/connect/{instance}
        $result = evolution_request('GET', "instance/connect/{$instance}", [], $config);

        if (!$result['success']) {
            $detalhe = $result['error'] ?? $result['raw'] ?? 'sem resposta';

            // Diagnóstico amigável
            if (str_contains($detalhe, 'cURL error')) {
                $msg = 'Não foi possível alcançar a Evolution API. Verifique se o servidor está online e se a URL está correta.';
            } elseif ($result['http_code'] === 401 || $result['http_code'] === 403) {
                $msg = 'API Key inválida ou sem permissão. Verifique a API Key nas configurações.';
            } elseif ($result['http_code'] === 404) {
                $msg = 'Instância "' . htmlspecialchars($instance) . '" não encontrada na Evolution API.';
            } else {
                $msg = 'Erro ao obter QR Code (HTTP ' . ($result['http_code'] ?? '?') . '): ' . $detalhe;
            }

            echo json_encode(['success' => false, 'error' => $msg]);
            break;
        }

        $data = $result['data'] ?? [];

        // A Evolution API pode retornar o base64 em diferentes campos conforme a versão
        $base64 = $data['base64']
            ?? $data['qrcode']['base64']
            ?? $data['qrcode']
            ?? null;

        $code = $data['code']
            ?? $data['qrcode']['code']
            ?? null;

        if (!$base64 && !$code) {
            // Instância pode já estar conectada ou em estado diferente
            $state = $data['instance']['state'] ?? $data['state'] ?? 'unknown';

            if (in_array($state, ['open', 'connected'])) {
                echo json_encode([
                    'success'   => true,
                    'connected' => true,
                    'state'     => 'open',
                    'message'   => 'WhatsApp conectado com sucesso!',
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'state'   => $state,
                    'error'   => 'QR Code não disponível. Estado da instância: ' . $state . '. Verifique sua Evolution API.',
                    'raw'     => json_encode($data),
                ]);
            }
            break;
        }

        // Garante que base64 vem com prefixo data URI
        if ($base64 && !str_starts_with($base64, 'data:')) {
            $base64 = 'data:image/png;base64,' . $base64;
        }

        echo json_encode([
            'success'   => true,
            'connected' => false,
            'state'     => 'connecting',
            'base64'    => $base64,
            'code'      => $code,
        ]);
        break;

    // ─── Desconectar instância ────────────────────────────────────────────────
    case 'logout':
        $instance = $config['instance_name'];
        $result   = evolution_request('DELETE', "instance/logout/{$instance}", [], $config);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Instância desconectada com sucesso.' : 'Erro ao desconectar: ' . ($result['raw'] ?? ''),
        ]);
        break;

    // ─── Reiniciar instância ──────────────────────────────────────────────────
    case 'restart':
        $instance = $config['instance_name'];
        $result   = evolution_request('PUT', "instance/restart/{$instance}", [], $config);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Instância reiniciada.' : 'Erro ao reiniciar.',
        ]);
        break;

    // ─── Diagnóstico completo ─────────────────────────────────────────────────
    case 'diagnostico':
        $instance = $config['instance_name'];
        $diag     = [];

        // 1. Testa acesso à API raiz
        $r = evolution_request('GET', '', [], $config);
        $diag['01_api_raiz'] = [
            'url'       => $r['url'] ?? ($config['api_url'] . '/'),
            'http_code' => $r['http_code'] ?? 0,
            'success'   => $r['success'],
            'raw'       => substr($r['raw'] ?? '', 0, 300),
        ];

        // 2. Lista instâncias existentes
        $r = evolution_request('GET', 'instance/fetchInstances', [], $config);
        $diag['02_listar_instancias'] = [
            'url'       => $r['url'] ?? '',
            'http_code' => $r['http_code'] ?? 0,
            'success'   => $r['success'],
            'data'      => $r['data'] ?? null,
            'raw'       => substr($r['raw'] ?? '', 0, 500),
        ];

        // 3. Estado da conexão da instância específica
        $r = evolution_request('GET', "instance/connectionState/{$instance}", [], $config);
        $diag['03_connection_state'] = [
            'url'       => $r['url'] ?? '',
            'http_code' => $r['http_code'] ?? 0,
            'success'   => $r['success'],
            'data'      => $r['data'] ?? null,
            'raw'       => substr($r['raw'] ?? '', 0, 500),
            'error'     => $r['error'] ?? null,
        ];

        // 4. Tenta buscar QR Code diretamente
        $r = evolution_request('GET', "instance/connect/{$instance}", [], $config);
        $diag['04_qrcode'] = [
            'url'       => $r['url'] ?? '',
            'http_code' => $r['http_code'] ?? 0,
            'success'   => $r['success'],
            'tem_base64'=> isset($r['data']['base64']),
            'raw'       => substr($r['raw'] ?? '', 0, 300),
        ];

        // 5. Configuração salva (sem expor api_key completa)
        $diag['05_config_salva'] = [
            'api_url'       => $config['api_url'],
            'instance_name' => $config['instance_name'],
            'api_key_len'   => strlen($config['api_key']) . ' chars, começa com: ' . substr($config['api_key'], 0, 6) . '...',
            'ativo'         => $config['ativo'],
        ];

        echo json_encode(['success' => true, 'diagnostico' => $diag], JSON_PRETTY_PRINT);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
}
