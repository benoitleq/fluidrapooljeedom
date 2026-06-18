"use strict";

/* ─── addCmdToTable : requis par plugin.template.js ──────────────────── */
function addCmdToTable(_cmd) {
    if (!isset(_cmd)) _cmd = {configuration: {}};
    if (!isset(_cmd.configuration)) _cmd.configuration = {};

    var tr = '';
    tr += '<td style="min-width:50px;width:70px;">';
    tr += '<span class="cmdAttr" data-l1key="id"></span>';
    tr += '</td>';
    tr += '<td>';
    tr += '<div class="row">';
    tr += '<div class="col-sm-5">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom}}">';
    tr += '</div>';
    tr += '<div class="col-sm-3">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="unite" placeholder="{{Unité}}">';
    tr += '</div>';
    tr += '</div>';
    tr += '</td>';
    tr += '<td>';
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '</td>';
    tr += '<td>';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/> {{Afficher}}</label></span> ';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/> {{Historiser}}</label></span> ';
    tr += '</td>';
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
    tr += '</td>';

    var newRow = document.createElement('tr');
    newRow.className = 'cmd';
    newRow.setAttribute('data-cmd_id', init(_cmd.id));
    newRow.innerHTML = tr;
    document.querySelector('#table_cmd tbody').appendChild(newRow);
    newRow.setJeeValues(_cmd, '.cmdAttr');
    jeedom.cmd.changeType(newRow, init(_cmd.subType));
}

/* ─── Découverte automatique ─────────────────────────────────────────── */
$(document).on('click', '#bt_discoverDevices', function () {
    var btn = $(this);
    btn.find('i').attr('class', 'fas fa-spinner fa-spin');
    $.ajax({
        type: 'POST',
        url: 'plugins/fluidrapool/core/php/fluidrapool.ajax.php',
        dataType: 'json',
        data: {action: 'discoverDevices'},
        error: function (req) {
            var msg = (req.responseJSON && req.responseJSON.result)
                ? req.responseJSON.result
                : req.responseText.substring(0, 300);
            jeedomUtils.showAlert({message: msg, level: 'danger'});
            btn.find('i').attr('class', 'fas fa-search');
        },
        success: function (data) {
            if (data.state === 'ok') {
                jeedomUtils.showAlert({message: data.result, level: 'success'});
                setTimeout(function () { window.location.reload(); }, 2000);
            } else {
                jeedomUtils.showAlert({message: data.result, level: 'danger'});
                btn.find('i').attr('class', 'fas fa-search');
            }
        }
    });
});

/* ─── Rafraîchir l'équipement affiché ────────────────────────────────── */
$(document).on('click', '#bt_refreshEquipment', function () {
    var id = $('.eqLogicAttr[data-l1key="id"]').val();
    if (!id) {
        jeedomUtils.showAlert({message: '{{Sauvegardez d\'abord l\'équipement}}', level: 'warning'});
        return;
    }
    $('#div_fluidra_result').show();
    $('#span_fluidra_result').html('<i class="fas fa-spinner fa-spin"></i> {{Rafraîchissement…}}');
    $.ajax({
        type: 'POST',
        url: 'plugins/fluidrapool/core/php/fluidrapool.ajax.php',
        dataType: 'json',
        data: {action: 'refreshEq', id: id},
        error: function (req) {
            var msg = (req.responseJSON && req.responseJSON.result)
                ? req.responseJSON.result
                : req.responseText.substring(0, 200);
            $('#span_fluidra_result').html(
                '<span class="label label-danger"><i class="fas fa-times"></i> ' + msg + '</span>'
            );
        },
        success: function (data) {
            $('#span_fluidra_result').html(data.state === 'ok'
                ? '<span class="label label-success"><i class="fas fa-check"></i> ' + data.result + '</span>'
                : '<span class="label label-danger"><i class="fas fa-times"></i> ' + data.result + '</span>'
            );
        }
    });
});
