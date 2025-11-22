<?php
/**
 * Manifest.json dinâmico - Detecta automaticamente o caminho base
 * Acesse: http://localhost/rh-privus/manifest.php ou http://servidor/rh/manifest.php
 */

header('Content-Type: application/json');

// Detecta o caminho base automaticamente
$basePath = '/rh'; // Padrão para produção

// Tenta detectar pelo REQUEST_URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestUri = strtok($requestUri, '?'); // Remove query string

// Detecta se está em /rh-privus/ (localhost) ou /rh/ (produção)
if (strpos($requestUri, '/rh-privus/') !== false || strpos($requestUri, '/rh-privus') !== false) {
    $basePath = '/rh-privus';
} elseif (strpos($requestUri, '/rh/') !== false || preg_match('#^/rh[^a-z]#', $requestUri)) {
    $basePath = '/rh';
} else {
    // Tenta pelo SCRIPT_NAME
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($scriptPath, '/rh-privus') !== false) {
        $basePath = '/rh-privus';
    } elseif (strpos($scriptPath, '/rh') !== false && strpos($scriptPath, '/rh-privus') === false) {
        $basePath = '/rh';
    } else {
        // Tenta pelo DOCUMENT_ROOT
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if (strpos($docRoot, 'rh-privus') !== false) {
            $basePath = '/rh-privus';
        }
    }
}

$manifest = [
    'name' => 'RH Privus',
    'short_name' => 'RH Privus',
    'description' => 'Sistema de Gestão de Recursos Humanos',
    'start_url' => $basePath . '/',
    'scope' => $basePath . '/',
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#009ef7',
    'orientation' => 'portrait-primary',
    'icons' => [
        [
            'src' => $basePath . '/assets/avatar-privus.png',
            'sizes' => '72x72',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . '/assets/avatar-privus.png',
            'sizes' => '96x96',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . '/assets/avatar-privus.png',
            'sizes' => '128x128',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . '/assets/avatar-privus.png',
            'sizes' => '144x144',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . '/assets/avatar-privus.png',
            'sizes' => '152x152',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . '/assets/avatar-privus.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . '/assets/avatar-privus.png',
            'sizes' => '384x384',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . '/assets/avatar-privus.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

