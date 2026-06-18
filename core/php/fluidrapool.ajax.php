<?php
try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception('{{401 - Accès non autorisé}}');
    }

    if (init('action') == 'discoverDevices') {
        $result = fluidrapool::discoverDevices();
        ajax::success($result);
    }

    if (init('action') == 'refreshAll') {
        $count = 0;
        foreach (eqLogic::byType('fluidrapool', true) as $eq) {
            if ($eq->getIsEnable()) {
                $eq->refreshData();
                $count++;
            }
        }
        ajax::success("{$count} équipement(s) rafraîchi(s)");
    }

    if (init('action') == 'refreshEq') {
        $eq = eqLogic::byId(init('id'));
        if (!is_object($eq)) {
            throw new Exception('{{Équipement introuvable}}');
        }
        $eq->refreshData();
        ajax::success('{{Équipement mis à jour}}');
    }

    if (init('action') == 'testConnection') {
        $email    = init('email');
        $password = init('password');
        if (empty($email) || empty($password)) {
            throw new Exception('Email et mot de passe requis');
        }
        $result = fluidrapool::testConnection($email, $password);
        ajax::success($result);
    }

    throw new Exception('{{Action non trouvée : }}' . init('action'));

} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
