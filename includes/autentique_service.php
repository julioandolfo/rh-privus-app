<?php
/**
 * Serviço de Integração com Autentique
 * Classe para comunicação com a API GraphQL do Autentique
 */

require_once __DIR__ . '/functions.php';

// Função para log de contratos (se não existir)
if (!function_exists('log_contrato')) {
    function log_contrato($message) {
        $logFile = __DIR__ . '/../logs/contratos.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

class AutentiqueService {
    private $apiKey;
    private $endpoint;
    private $sandbox;
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDB();
        $this->loadConfig();
    }
    
    /**
     * Carrega configurações do banco de dados
     */
    private function loadConfig() {
        $stmt = $this->pdo->query("SELECT * FROM autentique_config WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
        $config = $stmt->fetch();
        
        if (!$config) {
            throw new Exception('Autentique não configurado. Configure em Configurações > Autentique.');
        }
        
        $this->apiKey = $config['api_key'];
        $this->sandbox = (bool)$config['sandbox'];
        $this->endpoint = 'https://api.autentique.com.br/v2/graphql';
    }
    
    /**
     * Executa query/mutation GraphQL
     */
    private function executeGraphQL($query, $variables = []) {
        $ch = curl_init($this->endpoint);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'query' => $query,
                'variables' => $variables
            ]),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('Erro cURL: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('Erro HTTP ' . $httpCode . ': ' . $response);
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['errors'])) {
            $errorMessage = $data['errors'][0]['message'] ?? 'Erro desconhecido';
            throw new Exception('Erro GraphQL: ' . $errorMessage);
        }
        
        return $data['data'] ?? null;
    }
    
    /**
     * Cria documento no Autentique
     * 
     * @param string $nome Nome do documento
     * @param string $pdfBase64 PDF em base64
     * @param array $signatarios Array de signatários
     * @return array Dados do documento criado
     */
    public function criarDocumento($nome, $pdfBase64, $signatarios) {
        $signers = [];
        foreach ($signatarios as $index => $signatario) {
            $signers[] = [
                'email' => $signatario['email'],
                'action' => 'SIGN'
            ];
        }
        
        $query = '
            mutation CreateDocumentMutation(
                $document: DocumentInput!,
                $signers: [SignerInput!]!,
                $file: Upload!
            ) {
                createDocument(
                    document: $document,
                    signers: $signers,
                    file: $file
                ) {
                    id
                    name
                    created_at
                    signatures {
                        public_id
                        name
                        email
                        created_at
                        action { name }
                        link { short_link }
                    }
                }
            }
        ';
        
        $variables = [
            'document' => [
                'name' => $nome
            ],
            'signers' => $signers,
            'file' => null // Será enviado via multipart
        ];
        
        // Para envio de arquivo, precisamos usar multipart/form-data
        return $this->executeGraphQLWithFile($query, $variables, $pdfBase64);
    }
    
    /**
     * Executa GraphQL com upload de arquivo (multipart)
     */
    private function executeGraphQLWithFile($query, $variables, $fileBase64) {
        $ch = curl_init($this->endpoint);
        
        // Prepara o mapa de arquivo
        $operations = json_encode([
            'query' => $query,
            'variables' => $variables
        ]);
        
        $map = json_encode(['0' => ['variables.file']]);
        
        // Decodifica o base64 e salva temporariamente
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tempFile, base64_decode($fileBase64));
        
        $postFields = [
            'operations' => $operations,
            'map' => $map,
            '0' => new CURLFile($tempFile, 'application/pdf', 'contrato.pdf')
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Remove arquivo temporário
        @unlink($tempFile);
        
        if ($curlError) {
            throw new Exception('Erro cURL: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('Erro HTTP ' . $httpCode . ': ' . $response);
        }
        
        $data = json_decode($response, true);
        
        log_contrato("Autentique Response: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        if (isset($data['errors'])) {
            $errorMessage = $data['errors'][0]['message'] ?? 'Erro desconhecido';
            log_contrato("Autentique Error: " . $errorMessage);
            throw new Exception('Erro GraphQL: ' . $errorMessage);
        }
        
        $result = $data['data']['createDocument'] ?? null;
        log_contrato("Autentique createDocument result: " . json_encode($result, JSON_UNESCAPED_UNICODE));
        
        return $result;
    }
    
    /**
     * Consulta status do documento
     */
    public function consultarStatus($documentId) {
        $query = '
            query GetDocument($id: UUID!) {
                document(id: $id) {
                    id
                    name
                    created_at
                    signatures {
                        public_id
                        name
                        email
                        signed { created_at }
                        link { short_link }
                    }
                }
            }
        ';
        
        $variables = ['id' => $documentId];
        
        $result = $this->executeGraphQL($query, $variables);
        
        // Transforma para formato compatível com código existente
        $doc = $result['document'] ?? null;
        if ($doc && isset($doc['signatures'])) {
            $signers = [];
            foreach ($doc['signatures'] as $sig) {
                $signers[] = [
                    'id' => $sig['public_id'] ?? null,
                    'email' => $sig['email'] ?? null,
                    'signed' => !empty($sig['signed']),
                    'signedAt' => $sig['signed']['created_at'] ?? null,
                    'link' => $sig['link']['short_link'] ?? null
                ];
            }
            $doc['signers'] = $signers;
        }
        
        return $doc;
    }
    
    /**
     * Cria link público de assinatura
     */
    public function criarLinkPublico($documentId, $signerId, $expiresInDays = 30) {
        $query = '
            mutation CreateSignatureLink($documentId: ID!, $signerId: ID!) {
                createSignatureLink(documentId: $documentId, signerId: $signerId) {
                    link
                    expiresAt
                }
            }
        ';
        
        $variables = [
            'documentId' => $documentId,
            'signerId' => $signerId
        ];
        
        $result = $this->executeGraphQL($query, $variables);
        
        return $result['createSignatureLink'] ?? null;
    }
    
    /**
     * Cancela documento
     */
    public function cancelarDocumento($documentId) {
        $query = '
            mutation CancelDocument($id: ID!) {
                cancelDocument(id: $id) {
                    id
                    status
                }
            }
        ';
        
        $variables = ['id' => $documentId];
        
        $result = $this->executeGraphQL($query, $variables);
        
        return $result['cancelDocument'] ?? null;
    }
    
    /**
     * Reenvia notificação de assinatura
     */
    public function reenviarAssinatura($documentId, $signerId) {
        $query = '
            mutation ResendSignature($documentId: ID!, $signerId: ID!) {
                resendSignature(documentId: $documentId, signerId: $signerId) {
                    success
                    message
                }
            }
        ';
        
        $variables = [
            'documentId' => $documentId,
            'signerId' => $signerId
        ];
        
        $result = $this->executeGraphQL($query, $variables);
        
        return $result['resendSignature'] ?? null;
    }
    
    /**
     * Busca usuário atual (para teste de conexão)
     */
    public function buscarUsuarioAtual() {
        $query = '
            query {
                me {
                    id
                    name
                    email
                }
            }
        ';
        
        $result = $this->executeGraphQL($query);
        
        return $result['me'] ?? null;
    }
}

