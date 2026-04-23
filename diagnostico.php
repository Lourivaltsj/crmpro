<?php
/**
 * Arquivo de diagnóstico para testar a API
 */

header('Content-Type: text/plain');
echo "=== DIAGNÓSTICO DA API CRM ===\n\n";

// Verificar se a pasta data existe
$folder = 'data/';
echo "1. Verificando pasta data: ";
if (file_exists($folder)) {
    echo "✓ Existe\n";
} else {
    echo "✗ Não existe\n";
    mkdir($folder, 0777, true);
    echo "   Criada pasta data/\n";
}

// Verificar arquivos CSV
$files = [
    'clientes'   => $folder . 'clientes.csv',
    'vendedores' => $folder . 'vendedores.csv',
    'transacoes' => $folder . 'transacoes.csv'
];

echo "\n2. Verificando arquivos CSV:\n";
foreach ($files as $name => $path) {
    echo "   $name.csv: ";
    if (file_exists($path)) {
        $size = filesize($path);
        echo "✓ Existe ($size bytes)\n";

        // Mostrar primeiras linhas
        $handle = fopen($path, 'r');
        $line1 = fgets($handle);
        $line2 = fgets($handle);
        fclose($handle);

        echo "      Header: " . trim($line1) . "\n";
        if ($line2) {
            echo "      Primeira linha: " . trim($line2) . "\n";
        }
    } else {
        echo "✗ Não existe\n";
    }
}

// Testar leitura dos dados
echo "\n3. Testando leitura de dados:\n";

function readCSV($path) {
    $rows = [];
    if (!file_exists($path)) return $rows;
    if (($handle = fopen($path, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        if (!$headers) {
            fclose($handle);
            return $rows;
        }
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) === count($headers)) {
                $rows[] = array_combine($headers, $data);
            }
        }
        fclose($handle);
    }
    return $rows;
}

foreach ($files as $name => $path) {
    $data = readCSV($path);
    echo "   $name: " . count($data) . " registros\n";
}

echo "\n4. Informações do servidor:\n";
echo "   PHP Version: " . phpversion() . "\n";
echo "   Server: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "   Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "   Current Dir: " . __DIR__ . "\n";

echo "\n=== FIM DO DIAGNÓSTICO ===";
?>