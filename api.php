<?php
/**
 * API de Integração para CRM Vendas usando CSV
 * Substitui o MySQL por ficheiros de texto compatíveis com Excel
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// --- CONFIGURAÇÃO DOS FICHEIROS ---
$folder = 'data/';
if (!file_exists($folder)) mkdir($folder, 0777, true);

$files = [
    'clientes'   => $folder . 'clientes.csv',
    'vendedores' => $folder . 'vendedores.csv',
    'transacoes' => $folder . 'transacoes.csv'
];

// Inicializar ficheiros se não existirem ou estiverem vazios
foreach ($files as $key => $path) {
    $headers = ($key === 'clientes') ? "id,nome,nascimento,telefone,cpf,rg,endereco,referencia,limite\n" :
              (($key === 'vendedores') ? "id,nome,senha,nivel\n" :
              "id,cli_id,v_id,tipo,total,desc,forma,data\n");

    if (!file_exists($path) || filesize($path) === 0) {
        file_put_contents($path, $headers);
    }
}

$action = $_GET['action'] ?? '';

// Função auxiliar para ler CSV para Array
function readCSV($path) {
    $rows = [];
    if (!file_exists($path)) return $rows;
    if (($handle = fopen($path, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        if (!$headers || !isset($headers[0])) {
            fclose($handle);
            return $rows;
        }
        $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]); // Remove BOM
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) !== count($headers)) continue;
            $rows[] = array_combine($headers, $data);
        }
        fclose($handle);
    }
    return $rows;
}

// Função auxiliar para escrever no CSV
function appendCSV($path, $data) {
    $handle = fopen($path, 'a');
    fputcsv($handle, $data);
    fclose($handle);
}

$action = $_GET['action'] ?? '';

// --- ROTEAMENTO ---
switch ($action) {
    case 'getClientes':
        echo json_encode(readCSV($files['clientes']));
        break;

    case 'getVendedores':
        echo json_encode(readCSV($files['vendedores']));
        break;

    case 'getTransacoes':
        $t = readCSV($files['transacoes']);
        $c = readCSV($files['clientes']);
        $v = readCSV($files['vendedores']);
        
        // Mapear nomes para facilitar o front-end
        foreach ($t as &$row) {
            $clienteId = $row['cli_id'] ?? $row['cliente_id'] ?? null;
            $vendedorId = $row['v_id'] ?? $row['vendedor_id'] ?? null;
            $cli = array_filter($c, fn($item) => $item['id'] == $clienteId);
            $vend = array_filter($v, fn($item) => $item['id'] == $vendedorId);
            $row['cliente_nome'] = !empty($cli) ? reset($cli)['nome'] : 'Desconhecido';
            $row['vendedor_nome'] = !empty($vend) ? reset($vend)['nome'] : 'N/A';
        }
        echo json_encode($t);
        break;

    case 'addCliente':
        $input = json_decode(file_get_contents('php://input'), true);
        $all = readCSV($files['clientes']);
        $newId = count($all) > 0 ? max(array_column($all, 'id')) + 1 : 1;
        appendCSV($files['clientes'], [
            $newId, $input['nome'], $input['nascimento'] ?? '', $input['telefone'] ?? '', 
            $input['cpf'] ?? '', $input['rg'] ?? '', $input['endereco'] ?? '', $input['referencia'] ?? '', $input['limite'] ?? 0
        ]);
        echo json_encode(['success' => true]);
        break;

    case 'addVendedor':
        $input = json_decode(file_get_contents('php://input'), true);
        $all = readCSV($files['vendedores']);
        $newId = count($all) > 0 ? max(array_column($all, 'id')) + 1 : 1;
        appendCSV($files['vendedores'], [$newId, $input['nome'], $input['senha'], $input['nivel']]);
        echo json_encode(['success' => true]);
        break;

    case 'addTransacao':
        $input = json_decode(file_get_contents('php://input'), true);
        $all = readCSV($files['transacoes']);
        $newId = count($all) > 0 ? max(array_column($all, 'id')) + 1 : 1;
        $clienteId = $input['cli_id'] ?? $input['cliente_id'] ?? null;
        $vendedorId = $input['v_id'] ?? $input['vendedor_id'] ?? null;
        appendCSV($files['transacoes'], [
            $newId,
            $clienteId,
            $vendedorId,
            $input['tipo'],
            $input['total'],
            $input['desc'],
            $input['forma'],
            $input['data']
        ]);
        echo json_encode(['success' => true]);
        break;

    case 'updateCliente':
        $input = json_decode(file_get_contents('php://input'), true);
        $all = readCSV($files['clientes']);
        $updated = array_map(function($c) use ($input) {
            if ($c['id'] == $input['id']) {
                return array_merge($c, $input);
            }
            return $c;
        }, $all);
        // Rewrite the file
        $handle = fopen($files['clientes'], 'w');
        fputcsv($handle, ['id','nome','nascimento','telefone','cpf','rg','endereco','referencia','limite']);
        foreach ($updated as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        echo json_encode(['success' => true]);
        break;

    case 'deleteCliente':
        $input = json_decode(file_get_contents('php://input'), true);
        $all = readCSV($files['clientes']);
        $filtered = array_filter($all, fn($c) => $c['id'] != $input['id']);
        $handle = fopen($files['clientes'], 'w');
        fputcsv($handle, ['id','nome','nascimento','telefone','cpf','rg','endereco','referencia','limite']);
        foreach ($filtered as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        echo json_encode(['success' => true]);
        break;

    case 'deleteVendedor':
        $input = json_decode(file_get_contents('php://input'), true);
        $all = readCSV($files['vendedores']);
        $filtered = array_filter($all, fn($v) => $v['id'] != $input['id']);
        $handle = fopen($files['vendedores'], 'w');
        fputcsv($handle, ['id','nome','senha','nivel']);
        foreach ($filtered as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        echo json_encode(['success' => true]);
        break;

    case 'updateVendedor':
        $input = json_decode(file_get_contents('php://input'), true);
        $all = readCSV($files['vendedores']);
        $updated = array_map(function($v) use ($input) {
            if ($v['id'] == $input['id']) {
                return array_merge($v, $input);
            }
            return $v;
        }, $all);
        $handle = fopen($files['vendedores'], 'w');
        fputcsv($handle, ['id','nome','senha','nivel']);
        foreach ($updated as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        echo json_encode(['success' => true]);
        break;

    case 'deleteTransacao':
        $input = json_decode(file_get_contents('php://input'), true);
        $all = readCSV($files['transacoes']);
        $filtered = array_filter($all, fn($t) => $t['id'] != $input['id']);
        $handle = fopen($files['transacoes'], 'w');
        fputcsv($handle, ['id','cli_id','v_id','tipo','total','desc','forma','data']);
        foreach ($filtered as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        echo json_encode(['success' => true]);
        break;

    default:
}