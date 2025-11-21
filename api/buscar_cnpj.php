<?php
/**
 * API para buscar dados do CNPJ usando ReceitaWS
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$cnpj = $_GET['cnpj'] ?? '';
$cnpj = preg_replace('/[^0-9]/', '', $cnpj);

if (empty($cnpj) || strlen($cnpj) != 14) {
    echo json_encode(['success' => false, 'error' => 'CNPJ inválido']);
    exit;
}

try {
    // Verifica se curl está disponível
    if (!function_exists('curl_init')) {
        echo json_encode(['success' => false, 'error' => 'cURL não está disponível no servidor']);
        exit;
    }
    
    // Tenta primeiro com ReceitaWS
    $url = "https://www.receitaws.com.br/v1/cnpj/" . $cnpj;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Se houver erro no curl ou código diferente de 200, tenta API alternativa
    if ($response === false || $httpCode !== 200 || !empty($curlError)) {
        // Tenta API alternativa: BrasilAPI
        $url_alt = "https://brasilapi.com.br/api/cnpj/v1/" . $cnpj;
        
        $ch_alt = curl_init();
        curl_setopt_array($ch_alt, [
            CURLOPT_URL => $url_alt,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch_alt);
        $httpCode = curl_getinfo($ch_alt, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch_alt);
        curl_close($ch_alt);
        
        if ($response === false || $httpCode !== 200 || !empty($curlError)) {
            $errorMsg = !empty($curlError) ? $curlError : "Erro HTTP $httpCode";
            echo json_encode(['success' => false, 'error' => 'Erro ao conectar com a API: ' . $errorMsg]);
            exit;
        }
        
        $data = json_decode($response, true);
        
        // Formata dados da BrasilAPI
        if (isset($data['cnpj']) || isset($data['razao_social'])) {
            $endereco = $data['endereco'] ?? $data['address'] ?? [];
            
            // Estrutura pode variar entre APIs
            $logradouro = '';
            $numero = '';
            
            if (isset($endereco['logradouro'])) {
                $logradouro = $endereco['logradouro'];
                $numero = $endereco['numero'] ?? '';
            } elseif (isset($endereco['street'])) {
                $logradouro = $endereco['street'];
                $numero = $endereco['number'] ?? '';
            }
            
            $result = [
                'success' => true,
                'data' => [
                    'razao_social' => $data['razao_social'] ?? $data['company']['name'] ?? '',
                    'nome_fantasia' => $data['nome_fantasia'] ?? $data['fantasia'] ?? $data['company']['fantasia'] ?? '',
                    'cep' => preg_replace('/[^0-9]/', '', $endereco['cep'] ?? $endereco['zip'] ?? ''),
                    'logradouro' => $logradouro,
                    'numero' => $numero,
                    'complemento' => $endereco['complemento'] ?? $endereco['details'] ?? '',
                    'bairro' => $endereco['bairro'] ?? $endereco['district'] ?? '',
                    'cidade' => $endereco['municipio'] ?? $endereco['city'] ?? '',
                    'estado' => $endereco['uf'] ?? $endereco['state'] ?? '',
                    'telefone' => preg_replace('/[^0-9]/', '', $data['telefone'] ?? $data['phone'] ?? ''),
                    'email' => $data['email'] ?? ''
                ]
            ];
            
            echo json_encode($result);
            exit;
        }
    } else {
        // Processa resposta da ReceitaWS
        $data = json_decode($response, true);
        
        if (isset($data['status']) && $data['status'] === 'ERROR') {
            echo json_encode(['success' => false, 'error' => $data['message'] ?? 'CNPJ não encontrado']);
            exit;
        }
        
        // Formata os dados para retornar
        $result = [
            'success' => true,
            'data' => [
                'razao_social' => $data['nome'] ?? '',
                'nome_fantasia' => $data['fantasia'] ?? '',
                'cep' => preg_replace('/[^0-9]/', '', $data['cep'] ?? ''),
                'logradouro' => $data['logradouro'] ?? '',
                'numero' => $data['numero'] ?? '',
                'complemento' => $data['complemento'] ?? '',
                'bairro' => $data['bairro'] ?? '',
                'cidade' => $data['municipio'] ?? '',
                'estado' => $data['uf'] ?? '',
                'telefone' => preg_replace('/[^0-9]/', '', $data['telefone'] ?? ''),
                'email' => $data['email'] ?? ''
            ]
        ];
        
        echo json_encode($result);
        exit;
    }
    
    // Se chegou aqui, nenhuma API funcionou
    echo json_encode(['success' => false, 'error' => 'CNPJ não encontrado ou API indisponível']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro ao processar: ' . $e->getMessage()]);
}

