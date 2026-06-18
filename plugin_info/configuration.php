<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
?>
<form class="form-horizontal">
    <fieldset>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Compte Fluidra Connect}}</label>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Email}}</label>
            <div class="col-lg-4">
                <input class="configKey form-control" data-l1key="email" placeholder="votre@email.com" autocomplete="off" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Mot de passe}}</label>
            <div class="col-lg-4">
                <input type="password" class="configKey form-control" data-l1key="password" placeholder="••••••••" autocomplete="new-password" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Intervalle de rafraîchissement (minutes)}}</label>
            <div class="col-lg-4">
                <input class="configKey form-control" data-l1key="refresh_interval" placeholder="5" type="number" min="1" max="60" />
                <span class="help-block">{{Délai entre chaque mise à jour des données (défaut : 5 min)}}</span>
            </div>
        </div>
        <div class="form-group">
            <div class="col-lg-offset-4 col-lg-4">
                <a class="btn btn-primary" id="bt_testFluidraConnection">
                    <i class="fas fa-plug"></i> {{Tester la connexion}}
                </a>
            </div>
        </div>
        <div class="form-group" id="div_testResult" style="display:none;">
            <div class="col-lg-offset-4 col-lg-6">
                <div id="testResultContent" class="alert"></div>
            </div>
        </div>
    </fieldset>
</form>
<script>
$('#bt_testFluidraConnection').on('click', function () {
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Test en cours…');
    $('#div_testResult').hide();
    $.ajax({
        type: 'POST',
        url: 'plugins/fluidrapool/core/ajax/fluidrapool.ajax.php',
        dataType: 'json',
        data: {
            action: 'testConnection',
            email: $('.configKey[data-l1key=email]').val(),
            password: $('.configKey[data-l1key=password]').val()
        },
        success: function (data) {
            $('#div_testResult').show();
            if (data.state === 'ok') {
                $('#testResultContent')
                    .removeClass('alert-danger').addClass('alert-success')
                    .html('<i class="fas fa-check-circle"></i> ' + data.result);
            } else {
                $('#testResultContent')
                    .removeClass('alert-success').addClass('alert-danger')
                    .html('<i class="fas fa-times-circle"></i> ' + data.result);
            }
        },
        error: function (jqXHR) {
            $('#div_testResult').show();
            $('#testResultContent')
                .removeClass('alert-success').addClass('alert-danger')
                .html('<i class="fas fa-times-circle"></i> Erreur : ' + jqXHR.responseText);
        },
        complete: function () {
            btn.prop('disabled', false).html('<i class="fas fa-plug"></i> {{Tester la connexion}}');
        }
    });
});
</script>
