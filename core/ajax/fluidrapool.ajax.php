<?php
/**
 * Point d'entrée AJAX du plugin Fluidra Pool.
 * Ce fichier est INCLUS par core/ajax/plugin.ajax.php (Jeedom framework).
 * Jeedom a déjà : chargé core.inc.php, authentifié l'utilisateur (admin),
 * et chargé la classe fluidrapool via include_file().
 * Ne PAS re-bootstrapper ici.
 */

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
        ajax::success("{$count} équipement(s) rafraîchi(s)");
        break;

    case 'refreshEq':
        $id = init('id');
        $eq = eqLogic::byId($id);
        if (!is_object($eq)) {
            ajax::error('Équipement introuvable');
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
        $result = fluidrapool::testConnection($email, $password);
        ajax::success($result);
        break;

    default:
        ajax::error('Action inconnue : ' . $action);
}
