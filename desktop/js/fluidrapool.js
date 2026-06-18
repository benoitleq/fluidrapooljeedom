/* Plugin Fluidra Pool — JavaScript Jeedom Desktop */
"use strict";

/* Affichage d'un équipement depuis la liste de gauche */
$('#ul_eqLogic').on('click', 'a', function () {
    var id = $(this).attr('id');
    if (!id) return;
    jeedom.eqLogic.get({
        id: id,
        success: function (eqLogic) {
            var el = $('.eqLogic');
            $.each(eqLogic, function (key, value) {
                el.setValues({key: key, value: value}, '.eqLogicAttr');
            });
            $.each(eqLogic.configuration, function (key, value) {
                el.find('.configKey[data-l1key="' + key + '"]').val(value);
            });
            el.show();
            var activetab = el.find('.nav-tabs li.active a').attr('href');
            if (activetab === '#eqlogictab2') {
                loadCommandsTab(eqLogic.id);
            }
        }
    });
});

/* Sauvegarde via le framework Jeedom */
$('.eqLogicAction[data-action="save"]').on('click', function () {
    var eqLogic = $('.eqLogic').getValues('.eqLogicAttr');
    eqLogic.configuration = $('.eqLogic').getValues('.configKey');
    jeedom.eqLogic.save({
        eqLogics: [eqLogic],
        success: function () {
            toastr.success('{{Sauvegarde réussie}}');
        },
        error: function (err) {
            toastr.error('{{Erreur sauvegarde : }}' + err);
        }
    });
});

/* Découverte automatique — via core/ajax/plugin.ajax.php (framework Jeedom) */
$('#bt_discoverDevices').on('click', function () {
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{Découverte en cours…}}');
    $.ajax({
        type: 'POST',
        url: 'core/ajax/plugin.ajax.php',
        dataType: 'json',
        data: {
            plugin: 'fluidrapool',
            action: 'discoverDevices'
        },
        success: function (data) {
            if (data.state === 'ok') {
                toastr.success(data.result + ' — {{Rechargement…}}');
                setTimeout(function () { window.location.reload(); }, 2000);
            } else {
                toastr.error('{{Erreur : }}' + data.result);
                btn.prop('disabled', false).html('<i class="fas fa-search"></i> {{Découvrir les appareils}}');
            }
        },
        error: function (jqXHR) {
            var msg = jqXHR.responseText || jqXHR.statusText;
            toastr.error('{{Erreur AJAX : }}' + msg);
            btn.prop('disabled', false).html('<i class="fas fa-search"></i> {{Découvrir les appareils}}');
        }
    });
});

/* Rafraîchir tous les équipements */
$('#bt_refreshAllEquipments').on('click', function () {
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{Rafraîchissement…}}');
    $.ajax({
        type: 'POST',
        url: 'core/ajax/plugin.ajax.php',
        dataType: 'json',
        data: {
            plugin: 'fluidrapool',
            action: 'refreshAll'
        },
        success: function (data) {
            if (data.state === 'ok') {
                toastr.success(data.result);
            } else {
                toastr.error('{{Erreur : }}' + data.result);
            }
        },
        error: function (jqXHR) {
            toastr.error('{{Erreur AJAX : }}' + jqXHR.responseText);
        },
        complete: function () {
            btn.prop('disabled', false).html('<i class="fas fa-sync"></i> {{Tout rafraîchir}}');
        }
    });
});

/* Rafraîchir l'équipement affiché */
$('#bt_refreshEquipment').on('click', function () {
    var id = $('.eqLogicAttr[data-l1key="id"]').val();
    if (!id) { toastr.warning('{{Sauvegardez d\'abord l\'équipement}}'); return; }
    $.ajax({
        type: 'POST',
        url: 'core/ajax/plugin.ajax.php',
        dataType: 'json',
        data: {
            plugin: 'fluidrapool',
            action: 'refreshEq',
            id: id
        },
        success: function (data) {
            if (data.state === 'ok') {
                toastr.success(data.result);
            } else {
                toastr.error('{{Erreur : }}' + data.result);
            }
        }
    });
});

/* Chargement de l'onglet Commandes */
$('.nav-tabs a').on('shown.bs.tab', function (e) {
    if ($(e.target).attr('href') === '#eqlogictab2') {
        var id = $('.eqLogicAttr[data-l1key="id"]').val();
        if (id) loadCommandsTab(id);
    }
});

function loadCommandsTab(eqLogicId) {
    jeedom.cmd.getAll({
        eqLogic_id: eqLogicId,
        success: function (cmds) {
            var tbody = $('#table_cmd tbody').empty();
            $.each(cmds, function (i, cmd) {
                var tr = $('<tr>');
                tr.append($('<td>').text(cmd.name));
                tr.append($('<td>').text(cmd.type + ' / ' + cmd.subType));
                var valTd = $('<td>');
                if (cmd.type === 'info') {
                    valTd.text(cmd.currentValue !== undefined ? cmd.currentValue : '-');
                } else {
                    valTd.html('<a class="btn btn-xs btn-default bt_execCmd" data-cmd_id="' + cmd.id + '"><i class="fas fa-play"></i></a>');
                }
                tr.append(valTd);
                tr.append($('<td>').text(cmd.logicalId));
                tr.append($('<td>').html(
                    '<a class="btn btn-xs btn-default" onclick="jeedom.cmd.configure({id:' + cmd.id + '})"><i class="fas fa-cog"></i></a>'
                ));
                tbody.append(tr);
            });
        }
    });
}

$(document).on('click', '.bt_execCmd', function () {
    var cmdId = $(this).data('cmd_id');
    jeedom.cmd.execute({ id: cmdId, success: function () { toastr.success('{{Commande exécutée}}'); } });
});
