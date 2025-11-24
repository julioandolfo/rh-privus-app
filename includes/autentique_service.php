<?php
/**
 * Serviço de Integração com Autentique
 * Classe para comunicação com a API GraphQL do Autentique
 */

require_once __DIR__ . '/functions.php';

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
                'action' => 'SIGN',
                'email' => $signatario['email'],
                'position' => [
                    'x' => $signatario['x'] ?? 100,
                    'y' => $signatario['y'] ?? ($index * 150 + 100)
                ]
            ];
        }
        
        $query = '
            mutation CreateDocument($document: DocumentInput!) {
                createDocument(document: $document) {
                    id
                    token
                    link
                    signers {
                        id
                        email
                        link
                    }
                }
            }
        ';
        
        $variables = [
            'document' => [
                'name' => $nome,
                'file' => $pdfBase64,
                'signers' => $signers
            ]
        ];
        
        $result = $this->executeGraphQL($query, $variables);
        
        return $result['createDocument'] ?? null;
    }
    
    /**
     * Consulta status do documento
     */
    public function consultarStatus($documentId) {
        $query = '
            query GetDocument($id: ID!) {
                document(id: $id) {
                    id
                    status
                    link
                    signers {
                        id
                        email
                        signed
                        signedAt
                        link
                    }
                }
            }
        ';
        
        $variables = ['id' => $documentId];
        
        $result = $this->executeGraphQL($query, $variables);
        
        return $result['document'] ?? null;
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

