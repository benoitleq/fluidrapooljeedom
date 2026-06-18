<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('fluidrapool');
sendVarToJs('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
    <!-- Panneau liste des équipements -->
    <div class="col-lg-2 col-md-3 col-sm-4">
        <div class="bs-sidebar">
            <ul id="ul_eqLogic" class="nav nav-stacked">
                <?php foreach ($eqLogics as $eqLogic) : ?>
                    <li>
                        <a class="list-group-item<?= ($eqLogic->getIsEnable() == 0) ? ' disabled' : '' ?>"
                           id="<?= $eqLogic->getId() ?>">
                            <?php if ($eqLogic->getImage() != '') : ?>
                                <img class="lazy" data-original="<?= $eqLogic->getImage() ?>" src="img/img_loading.gif" />
                            <?php else : ?>
                                <i class="fas fa-swimming-pool"></i>
                            <?php endif ?>
                            <?= $eqLogic->getHumanName(true, true) ?>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>

        <div class="btn-group-vertical" style="width:100%">
            <a class="btn btn-default btn-sm eqLogicAction" data-action="add">
                <i class="fas fa-plus-circle"></i> {{Ajouter}}
            </a>
            <a class="btn btn-default btn-sm" id="bt_discoverDevices">
                <i class="fas fa-search"></i> {{Découvrir les appareils}}
            </a>
            <a class="btn btn-default btn-sm" id="bt_refreshAllEquipments">
                <i class="fas fa-sync"></i> {{Tout rafraîchir}}
            </a>
        </div>
    </div>

    <!-- Panneau configuration équipement -->
    <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailContainer">

        <legend><i class="fas fa-water"></i> {{Mes équipements Fluidra Pool}}</legend>

        <?php
        if (count($eqLogics) == 0) {
            echo '<div class="alert alert-warning">';
            echo '<i class="fas fa-info-circle"></i> ';
            echo '{{Aucun équipement. Configurez vos identifiants dans la configuration du plugin, puis cliquez sur "Découvrir les appareils".}}';
            echo '</div>';
        }
        ?>

        <div class="eqLogicThumbnailDisplay">
            <?php foreach ($eqLogics as $eqLogic) :
                $deviceType = $eqLogic->getConfiguration('device_type', 'unknown');
                $icons = [
                    'pool'        => 'fa-swimming-pool',
                    'pump'        => 'fa-water',
                    'heat_pump'   => 'fa-thermometer-half',
                    'light'       => 'fa-lightbulb',
                    'chlorinator' => 'fa-flask',
                    'sensor'      => 'fa-tachometer-alt',
                ];
                $icon = $icons[$deviceType] ?? 'fa-cogs';
                ?>
                <div class="cursor eqLogicDisplayCard<?= ($eqLogic->getIsEnable() == 0) ? ' opacity05' : '' ?>"
                     data-eqlogic_id="<?= $eqLogic->getId() ?>">
                    <center>
                        <i class="fas <?= $icon ?> fa-3x"></i>
                        <br/>
                        <b><?= $eqLogic->getHumanName(true, true) ?></b>
                        <br/>
                        <small class="text-muted"><?= jeedom::toHumanReadable(translate::exec($deviceType, 'fluidrapool')) ?></small>
                    </center>
                </div>
            <?php endforeach ?>
        </div>

        <!-- Formulaire de configuration d'un équipement -->
        <div class="eqLogic" style="display:none;">
            <div class="input-group pull-right" style="display:inline-flex">
                <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure">
                    <i class="fas fa-cogs"></i> {{Configuration avancée}}
                </a>
                <a class="btn btn-sm btn-success eqLogicAction roundedRight" data-action="save">
                    <i class="fas fa-check-circle"></i> {{Sauvegarder}}
                </a>
            </div>
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active">
                    <a href="#eqlogictab1" aria-controls="home" role="tab" data-toggle="tab">
                        <i class="fas fa-cogs"></i> {{Equipement}}
                    </a>
                </li>
                <li role="presentation">
                    <a href="#eqlogictab2" aria-controls="profile" role="tab" data-toggle="tab">
                        <i class="fas fa-list-alt"></i> {{Commandes}}
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Onglet Équipement -->
                <div role="tabpanel" class="tab-pane active" id="eqlogictab1">
                    <br/>
                    <div class="form-horizontal">
                        <fieldset>
                            <!-- Paramètres standards Jeedom -->
                            <?php include_file('desktop', 'eqLogic', 'php'); ?>

                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Type d\'appareil}}</label>
                                <div class="col-sm-3">
                                    <select class="configKey form-control" data-l1key="device_type" disabled>
                                        <option value="pool">{{Piscine (données globales)}}</option>
                                        <option value="pump">{{Pompe}}</option>
                                        <option value="heat_pump">{{Pompe à chaleur}}</option>
                                        <option value="light">{{Éclairage}}</option>
                                        <option value="chlorinator">{{Électrolyseur}}</option>
                                        <option value="sensor">{{Sonde}}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{ID Piscine}}</label>
                                <div class="col-sm-3">
                                    <input class="configKey form-control" data-l1key="pool_id" readonly />
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{ID Appareil}}</label>
                                <div class="col-sm-3">
                                    <input class="configKey form-control" data-l1key="device_id" readonly />
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="col-sm-offset-3 col-sm-3">
                                    <a class="btn btn-primary" id="bt_refreshEquipment">
                                        <i class="fas fa-sync"></i> {{Rafraîchir cet équipement}}
                                    </a>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                </div>

                <!-- Onglet Commandes -->
                <div role="tabpanel" class="tab-pane" id="eqlogictab2">
                    <br/>
                    <table id="table_cmd" class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th>{{Nom}}</th>
                                <th>{{Type}}</th>
                                <th>{{Valeur}}</th>
                                <th>{{Options}}</th>
                                <th>{{Actions}}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
