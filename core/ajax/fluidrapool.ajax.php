<?php
try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception('401 - Accès non autorisé');
    }

    $action = init('action');

    switch ($action) {
        case 'discoverDevices':
            fluidrapool::discoverDevices();
            ajax::success('Découverte terminée');
            break;

        case 'refreshAll':
            foreach (eqLogic::byType('fluidrapool', true) as $eq) {
                if ($eq->getIsEnable()) {
                    $eq->refreshData();
                }
            }
            ajax::success('Rafraîchissement effectué');
            break;

        case 'refreshEq':
            $id = init('id');
            $eq = eqLogic::byId($id);
            if (!is_object($eq)) {
                throw new Exception('Équipement introuvable');
            }
            $eq->refreshData();
            ajax::success('Rafraîchi');
            break;

        case 'testConnection':
            $email    = init('email');
            $password = init('password');
            if (empty($email) || empty($password)) {
                ajax::error('Email et mot de passe requis');
            }
            $scriptPath = dirname(__FILE__) . '/../../resources/fluidra_api.py';
            $tokenFile  = jeedom::getTmpFolder('fluidrapool') . '/token_test.json';
            $cmd        = 'FLUIDRA_PASSWORD=' . escapeshellarg($password)
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
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
