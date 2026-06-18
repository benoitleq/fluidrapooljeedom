<?php
try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    // Chargement explicite de la classe du plugin (nécessaire dans certaines versions Jeedom)
    require_once dirname(__FILE__) . '/../class/fluidrapool.class.php';

    if (!isConnect('admin')) {
        throw new Exception('401 - Accès non autorisé');
    }

    $action = init('action');

    switch ($action) {
        case 'discoverDevices':
            $result = fluidrapool::discoverDevices();
            ajax::success($result);
            break;

        case 'refreshAll':
            $count = 0;
            foreach (eqLogic::byType('fluidrapool', true) as $eq) {
                if ($eq->getIsEnable()) {
                    $eq->refreshData();
                    $count++;
                }
            }
            ajax::success("$count équipement(s) rafraîchi(s)");
            break;

        case 'refreshEq':
            $id = init('id');
            $eq = eqLogic::byId($id);
            if (!is_object($eq)) {
                throw new Exception('Équipement introuvable');
            }
            $eq->refreshData();
            ajax::success('Équipement mis à jour');
            break;

        case 'testConnection':
            $email    = init('email');
            $password = init('password');
            if (empty($email) || empty($password)) {
                ajax::error('Email et mot de passe requis');
            }
            $scriptPath = dirname(__FILE__) . '/../../resources/fluidra_api.py';
            $tmpDir     = jeedom::getTmpFolder('fluidrapool');
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }
            $tokenFile = $tmpDir . '/token_test.json';
            $cmd       = 'FLUIDRA_PASSWORD=' . escapeshellarg($password)
                       . ' python3 ' . escapeshellarg($scriptPath)
                       . ' --email '      . escapeshellarg($email)
                       . ' --token-file ' . escapeshellarg($tokenFile)
                       . ' --action get_all 2>/tmp/fluidrapool_test_error.log';
            $output = shell_exec($cmd);
            if (!$output) {
                $err = @file_get_contents('/tmp/fluidrapool_test_error.log');
                ajax::error('Connexion échouée. ' . $err);
            }
            $data = json_decode($output, true);
            if (!$data || isset($data['error'])) {
                ajax::error($data['error'] ?? 'Réponse invalide du script');
            }
            $nbPools   = count($data['pools'] ?? []);
            $nbDevices = 0;
            foreach ($data['pools'] ?? [] as $pool) {
                $nbDevices += count($pool['devices'] ?? []);
            }
            ajax::success("Connexion réussie ! {$nbPools} piscine(s), {$nbDevices} appareil(s) trouvé(s).");
            break;

        default:
            throw new Exception("Action inconnue : {$action}");
    }
} catch (\Throwable $e) {
    ajax::error(displayException($e), $e->getCode());
}
