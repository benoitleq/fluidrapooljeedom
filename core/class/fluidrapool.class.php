<?php

class fluidrapool extends eqLogic {

    // ------ Constantes composants API Fluidra ------
    const COMP_PUMP_ONOFF       = 9;
    const COMP_AUTO_MODE        = 10;
    const COMP_PUMP_SPEED       = 11;
    const COMP_HP_ONOFF         = 13;
    const COMP_HP_PRESET        = 14;
    const COMP_HP_SETPOINT      = 15;
    const COMP_HP_MODE          = 16;
    const COMP_HP_PRESET_Z550   = 17;
    const COMP_HP_ONOFF_ALT     = 21;
    const COMP_HP_STATE         = 61;
    const COMP_LIGHT_ONOFF      = 11;
    const COMP_LIGHT_BRIGHTNESS = 17;
    const COMP_LIGHT_EFFECT     = 18;
    const COMP_LIGHT_COLOR      = 45;

    const PUMP_SPEED_LEVELS = [0 => 45, 1 => 65, 2 => 100];
    const HP_MODES   = [0 => 'heating', 1 => 'cooling', 2 => 'auto'];
    const HP_PRESETS = [0 => 'silence', 1 => 'smart', 2 => 'boost'];

    // ------ Cycle cron principal ------

    public static function cron() {
        foreach (eqLogic::byType('fluidrapool', true) as $eqLogic) {
            if ($eqLogic->getIsEnable() != 1) continue;
            try {
                $eqLogic->refreshData();
            } catch (Exception $e) {
                log::add('fluidrapool', 'error', 'Cron error on ' . $eqLogic->getName() . ' : ' . $e->getMessage());
            }
        }
    }

    public static function cron5() {
        $interval = (int)config::byKey('refresh_interval', 'fluidrapool', 5);
        if ($interval <= 5) {
            self::cron();
        }
    }

    // ------ Appel Python API ------

    private static function getPythonPath() {
        $candidates = ['python3', 'python'];
        foreach ($candidates as $py) {
            $out = shell_exec("which $py 2>/dev/null || command -v $py 2>/dev/null");
            if (trim($out) !== '') return trim($out);
        }
        return 'python3';
    }

    private static function callApi(array $args) {
        $email    = config::byKey('email', 'fluidrapool', '');
        $password = config::byKey('password', 'fluidrapool', '');

        if (empty($email) || empty($password)) {
            throw new Exception('Identifiants Fluidra non configurés');
        }

        $scriptPath = dirname(__FILE__) . '/../../resources/fluidra_api.py';
        $tmpDir     = jeedom::getTmpFolder('fluidrapool');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $tokenFile = $tmpDir . '/token_cache.json';
        $python    = self::getPythonPath();

        $cmdArgs = array_merge([
            escapeshellarg($python),
            escapeshellarg($scriptPath),
            '--email',    escapeshellarg($email),
            '--token-file', escapeshellarg($tokenFile),
        ], $args);

        $env    = 'FLUIDRA_PASSWORD=' . escapeshellarg($password);
        $cmd    = $env . ' ' . implode(' ', $cmdArgs) . ' 2>/tmp/fluidrapool_error.log';
        $output = shell_exec($cmd);

        if ($output === null || $output === '') {
            $err = @file_get_contents('/tmp/fluidrapool_error.log');
            throw new Exception('Pas de réponse du script Python. ' . $err);
        }

        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Réponse invalide du script : ' . substr($output, 0, 300));
        }
        if (isset($data['error'])) {
            throw new Exception($data['error']);
        }
        return $data;
    }

    // ------ Découverte et synchro des équipements ------

    public static function discoverDevices() {
        $data = self::callApi(['--action', 'get_all']);
        if (!isset($data['pools']) || count($data['pools']) === 0) {
            throw new Exception('Aucune piscine trouvée dans votre compte Fluidra Connect.');
        }

        $nbPools   = 0;
        $nbDevices = 0;

        foreach ($data['pools'] as $pool) {
            $poolId   = $pool['id'];
            $poolName = $pool['name'] ?? "Piscine $poolId";
            $nbPools++;

            self::syncPoolEquipment($poolId, $poolName, $pool);

            foreach ($pool['devices'] ?? [] as $device) {
                self::syncDeviceEquipment($poolId, $poolName, $device);
                $nbDevices++;
            }
        }

        return "{$nbPools} piscine(s) et {$nbDevices} appareil(s) configurés avec succès.";
    }

    private static function syncPoolEquipment($poolId, $poolName, $pool) {
        $logicalId = 'pool_' . $poolId;
        $eqLogic   = eqLogic::byLogicalId($logicalId, 'fluidrapool');
        if (!is_object($eqLogic)) {
            $eqLogic = new fluidrapool();
            $eqLogic->setLogicalId($logicalId);
            $eqLogic->setEqType_name('fluidrapool');
            $eqLogic->setIsVisible(1);
            $eqLogic->setIsEnable(1);
        }
        $eqLogic->setName($poolName);
        $eqLogic->setConfiguration('pool_id', $poolId);
        $eqLogic->setConfiguration('device_type', 'pool');
        $eqLogic->save();

        self::ensurePoolCommands($eqLogic);
    }

    private static function syncDeviceEquipment($poolId, $poolName, $device) {
        $deviceId   = $device['device_id'] ?? $device['id'] ?? null;
        if (!$deviceId) return;
        $deviceType = $device['type'] ?? 'unknown';
        $deviceName = $device['name'] ?? "Device $deviceId";

        $logicalId = 'device_' . $deviceId;
        $eqLogic   = eqLogic::byLogicalId($logicalId, 'fluidrapool');
        if (!is_object($eqLogic)) {
            $eqLogic = new fluidrapool();
            $eqLogic->setLogicalId($logicalId);
            $eqLogic->setEqType_name('fluidrapool');
            $eqLogic->setIsVisible(1);
            $eqLogic->setIsEnable(1);
        }
        $eqLogic->setName($poolName . ' - ' . $deviceName);
        $eqLogic->setConfiguration('pool_id', $poolId);
        $eqLogic->setConfiguration('device_id', $deviceId);
        $eqLogic->setConfiguration('device_type', $deviceType);
        $eqLogic->setConfiguration('device_name', $deviceName);
        $eqLogic->save();

        switch ($deviceType) {
            case 'pump':       self::ensurePumpCommands($eqLogic); break;
            case 'heat_pump':  self::ensureHeatPumpCommands($eqLogic); break;
            case 'light':      self::ensureLightCommands($eqLogic); break;
            case 'chlorinator': self::ensureChlorinatorCommands($eqLogic); break;
            default:           self::ensureGenericCommands($eqLogic); break;
        }
    }

    // ------ Création des commandes ------

    private static function getOrCreateCmd($eqLogic, $logicalId, $name, $type, $subtype, $extra = []) {
        $cmd = $eqLogic->getCmd(null, $logicalId);
        if (!is_object($cmd)) {
            $cmd = new fluidrapoolCmd();
            $cmd->setEqLogic_id($eqLogic->getId());
            $cmd->setLogicalId($logicalId);
        }
        $cmd->setName($name);
        $cmd->setType($type);
        $cmd->setSubType($subtype);
        foreach ($extra as $k => $v) {
            $cmd->setConfiguration($k, $v);
        }
        $cmd->save();
        return $cmd;
    }

    private static function ensurePoolCommands($eqLogic) {
        self::getOrCreateCmd($eqLogic, 'pool_status',      '{{Statut piscine}}',        'info', 'string');
        self::getOrCreateCmd($eqLogic, 'weather_temp',     '{{Température extérieure}}', 'info', 'numeric', ['unite' => '°C']);
        self::getOrCreateCmd($eqLogic, 'pool_location',    '{{Localisation}}',           'info', 'string');
        self::getOrCreateCmd($eqLogic, 'water_ph',         '{{pH eau}}',                 'info', 'numeric');
        self::getOrCreateCmd($eqLogic, 'water_orp',        '{{Potentiel rédox (ORP)}}',  'info', 'numeric', ['unite' => 'mV']);
        self::getOrCreateCmd($eqLogic, 'water_temp',       '{{Température eau}}',        'info', 'numeric', ['unite' => '°C']);
        self::getOrCreateCmd($eqLogic, 'water_salinity',   '{{Salinité}}',               'info', 'numeric', ['unite' => 'g/L']);
        self::getOrCreateCmd($eqLogic, 'pool_refresh',     '{{Rafraîchir}}',             'action', 'other');
    }

    private static function ensurePumpCommands($eqLogic) {
        self::getOrCreateCmd($eqLogic, 'pump_status',    '{{Etat pompe}}',     'info',   'binary');
        self::getOrCreateCmd($eqLogic, 'pump_speed',     '{{Vitesse}}',        'info',   'string');
        self::getOrCreateCmd($eqLogic, 'pump_speed_pct', '{{Vitesse (%)}}',    'info',   'numeric', ['unite' => '%']);
        self::getOrCreateCmd($eqLogic, 'pump_auto',      '{{Mode auto}}',      'info',   'binary');
        self::getOrCreateCmd($eqLogic, 'pump_on',        '{{Marche}}',         'action', 'other');
        self::getOrCreateCmd($eqLogic, 'pump_off',       '{{Arrêt}}',          'action', 'other');
        self::getOrCreateCmd($eqLogic, 'pump_speed_low',  '{{Vitesse basse}}',  'action', 'other');
        self::getOrCreateCmd($eqLogic, 'pump_speed_med',  '{{Vitesse moyenne}}', 'action', 'other');
        self::getOrCreateCmd($eqLogic, 'pump_speed_high', '{{Vitesse haute}}',  'action', 'other');
        self::getOrCreateCmd($eqLogic, 'pump_auto_on',   '{{Mode auto ON}}',   'action', 'other');
        self::getOrCreateCmd($eqLogic, 'pump_auto_off',  '{{Mode auto OFF}}',  'action', 'other');
        self::getOrCreateCmd($eqLogic, 'pump_refresh',   '{{Rafraîchir}}',     'action', 'other');
    }

    private static function ensureHeatPumpCommands($eqLogic) {
        self::getOrCreateCmd($eqLogic, 'hp_status',       '{{Etat PAC}}',               'info',   'binary');
        self::getOrCreateCmd($eqLogic, 'hp_state',        '{{Mode de fonctionnement}}',  'info',   'string');
        self::getOrCreateCmd($eqLogic, 'hp_target_temp',  '{{Température consigne}}',    'info',   'numeric', ['unite' => '°C']);
        self::getOrCreateCmd($eqLogic, 'hp_current_temp', '{{Température actuelle}}',    'info',   'numeric', ['unite' => '°C']);
        self::getOrCreateCmd($eqLogic, 'hp_mode',         '{{Mode (chauffe/refroid)}}',  'info',   'string');
        self::getOrCreateCmd($eqLogic, 'hp_preset',       '{{Preset}}',                  'info',   'string');
        self::getOrCreateCmd($eqLogic, 'hp_on',           '{{Marche PAC}}',              'action', 'other');
        self::getOrCreateCmd($eqLogic, 'hp_off',          '{{Arrêt PAC}}',               'action', 'other');
        self::getOrCreateCmd($eqLogic, 'hp_set_temp',     '{{Régler température}}',      'action', 'slider', ['minValue' => 7, 'maxValue' => 40, 'step' => 1]);
        self::getOrCreateCmd($eqLogic, 'hp_mode_heat',    '{{Mode chauffage}}',          'action', 'other');
        self::getOrCreateCmd($eqLogic, 'hp_mode_cool',    '{{Mode refroidissement}}',    'action', 'other');
        self::getOrCreateCmd($eqLogic, 'hp_mode_auto',    '{{Mode automatique}}',        'action', 'other');
        self::getOrCreateCmd($eqLogic, 'hp_preset_silence', '{{Preset silence}}',        'action', 'other');
        self::getOrCreateCmd($eqLogic, 'hp_preset_smart',   '{{Preset smart}}',          'action', 'other');
        self::getOrCreateCmd($eqLogic, 'hp_preset_boost',   '{{Preset boost}}',          'action', 'other');
        self::getOrCreateCmd($eqLogic, 'hp_refresh',      '{{Rafraîchir}}',              'action', 'other');
    }

    private static function ensureLightCommands($eqLogic) {
        self::getOrCreateCmd($eqLogic, 'light_status',     '{{Etat lumière}}',  'info',   'binary');
        self::getOrCreateCmd($eqLogic, 'light_brightness', '{{Luminosité}}',    'info',   'numeric', ['unite' => '%']);
        self::getOrCreateCmd($eqLogic, 'light_on',         '{{Allumer}}',       'action', 'other');
        self::getOrCreateCmd($eqLogic, 'light_off',        '{{Éteindre}}',      'action', 'other');
        self::getOrCreateCmd($eqLogic, 'light_set_brightness', '{{Régler luminosité}}', 'action', 'slider', ['minValue' => 0, 'maxValue' => 100]);
        self::getOrCreateCmd($eqLogic, 'light_refresh',    '{{Rafraîchir}}',    'action', 'other');
    }

    private static function ensureChlorinatorCommands($eqLogic) {
        self::getOrCreateCmd($eqLogic, 'chlor_status',  '{{Etat électrolyseur}}', 'info',   'binary');
        self::getOrCreateCmd($eqLogic, 'chlor_on',      '{{Marche}}',             'action', 'other');
        self::getOrCreateCmd($eqLogic, 'chlor_off',     '{{Arrêt}}',              'action', 'other');
        self::getOrCreateCmd($eqLogic, 'chlor_refresh', '{{Rafraîchir}}',         'action', 'other');
    }

    private static function ensureGenericCommands($eqLogic) {
        self::getOrCreateCmd($eqLogic, 'device_status',  '{{Etat}}',        'info',   'binary');
        self::getOrCreateCmd($eqLogic, 'device_refresh', '{{Rafraîchir}}',  'action', 'other');
    }

    // ------ Rafraîchissement des données ------

    public function refreshData() {
        $deviceType = $this->getConfiguration('device_type', '');
        $data       = self::callApi(['--action', 'get_all']);

        switch ($deviceType) {
            case 'pool':       $this->updatePoolData($data); break;
            case 'pump':       $this->updateDeviceData($data, 'pump'); break;
            case 'heat_pump':  $this->updateDeviceData($data, 'heat_pump'); break;
            case 'light':      $this->updateDeviceData($data, 'light'); break;
            case 'chlorinator': $this->updateDeviceData($data, 'chlorinator'); break;
            default:           $this->updateDeviceData($data, ''); break;
        }
    }

    private function updatePoolData($data) {
        $poolId = $this->getConfiguration('pool_id', '');
        $pool   = null;
        foreach ($data['pools'] ?? [] as $p) {
            if ($p['id'] == $poolId) { $pool = $p; break; }
        }
        if (!$pool) return;

        $this->checkAndUpdateCmd('pool_status', $pool['state'] ?? 'unknown');

        $statusData = $pool['status_data'] ?? [];
        $weather    = $statusData['weather'] ?? [];
        if (($weather['status'] ?? '') === 'ok') {
            $weatherVal = $weather['value'] ?? [];
            $tempK      = $weatherVal['current']['main']['temp'] ?? null;
            if ($tempK !== null) {
                $this->checkAndUpdateCmd('weather_temp', round($tempK - 273.15, 1));
            }
        }

        $geo = $pool['geolocation'] ?? [];
        if (!empty($geo)) {
            $loc = trim(($geo['locality'] ?? '') . ' ' . ($geo['countryCode'] ?? ''));
            $this->checkAndUpdateCmd('pool_location', $loc);
        }

        // Qualité eau depuis telemetry
        $wq = $pool['water_quality'] ?? [];
        if (!empty($wq)) {
            $this->checkAndUpdateCmd('water_ph',       $wq['ph'] ?? '');
            $this->checkAndUpdateCmd('water_orp',      $wq['orp'] ?? '');
            $this->checkAndUpdateCmd('water_temp',     $wq['temperature'] ?? '');
            $this->checkAndUpdateCmd('water_salinity', $wq['salinity'] ?? '');
        }

        $this->setStatus(true);
    }

    private function updateDeviceData($data, $deviceType) {
        $poolId   = $this->getConfiguration('pool_id', '');
        $deviceId = $this->getConfiguration('device_id', '');
        $device   = null;

        foreach ($data['pools'] ?? [] as $pool) {
            if ($pool['id'] != $poolId) continue;
            foreach ($pool['devices'] ?? [] as $d) {
                if (($d['device_id'] ?? $d['id'] ?? '') == $deviceId) {
                    $device = $d;
                    break 2;
                }
            }
        }

        if (!$device) {
            $this->setStatus(false);
            return;
        }

        $this->setStatus($device['online'] ?? false);
        $components = $device['components'] ?? [];

        switch ($deviceType) {
            case 'pump':      $this->applyPumpState($device, $components); break;
            case 'heat_pump': $this->applyHeatPumpState($device, $components); break;
            case 'light':     $this->applyLightState($device, $components); break;
            case 'chlorinator': $this->applyChlorinatorState($device, $components); break;
            default:
                $this->checkAndUpdateCmd('device_status', $device['online'] ?? 0);
                break;
        }
    }

    private function applyPumpState($device, $components) {
        $comp9  = $components[self::COMP_PUMP_ONOFF] ?? $components['9'] ?? [];
        $comp10 = $components[self::COMP_AUTO_MODE]  ?? $components['10'] ?? [];
        $comp11 = $components[self::COMP_PUMP_SPEED]  ?? $components['11'] ?? [];

        $isOn     = (int)($comp9['reportedValue']  ?? $device['is_running'] ?? 0);
        $autoMode = (int)($comp10['reportedValue'] ?? $device['auto_mode_enabled'] ?? 0);
        $speedLvl = (int)($comp11['reportedValue'] ?? $device['speed_percent'] ?? 0);

        $speedPct   = self::PUMP_SPEED_LEVELS[$speedLvl] ?? 45;
        $speedLabel = $speedPct . '%';

        $this->checkAndUpdateCmd('pump_status',    $isOn);
        $this->checkAndUpdateCmd('pump_auto',      $autoMode);
        $this->checkAndUpdateCmd('pump_speed',     $speedLabel);
        $this->checkAndUpdateCmd('pump_speed_pct', $speedPct);
    }

    private function applyHeatPumpState($device, $components) {
        $comp13 = $components[self::COMP_HP_ONOFF]    ?? $components['13'] ?? [];
        $comp21 = $components[self::COMP_HP_ONOFF_ALT] ?? $components['21'] ?? [];
        $comp15 = $components[self::COMP_HP_SETPOINT]  ?? $components['15'] ?? [];
        $comp16 = $components[self::COMP_HP_MODE]      ?? $components['16'] ?? [];
        $comp14 = $components[self::COMP_HP_PRESET]    ?? $components['14'] ?? [];
        $comp17 = $components[self::COMP_HP_PRESET_Z550] ?? $components['17'] ?? [];
        $comp61 = $components[self::COMP_HP_STATE]     ?? $components['61'] ?? [];

        $isOn     = (int)($comp13['reportedValue'] ?? $comp21['reportedValue'] ?? $device['is_running'] ?? 0);
        $setpointRaw = $comp15['reportedValue'] ?? null;
        $targetTemp  = $setpointRaw !== null ? round($setpointRaw / 10, 1) : null;

        $modeVal    = $comp16['reportedValue'] ?? null;
        $modeStr    = $modeVal !== null ? (self::HP_MODES[$modeVal] ?? 'unknown') : 'unknown';
        $presetVal  = $comp17['reportedValue'] ?? $comp14['reportedValue'] ?? null;
        $presetStr  = $presetVal !== null ? (self::HP_PRESETS[$presetVal] ?? 'unknown') : 'unknown';

        $stateMap = [0 => 'idle', 2 => 'heating', 3 => 'cooling', 11 => 'no_flow'];
        $stateVal = $comp61['reportedValue'] ?? null;
        $stateStr = $stateVal !== null ? ($stateMap[$stateVal] ?? 'unknown') : 'unknown';

        $currentTemp = $device['current_temperature'] ?? $device['water_temperature'] ?? null;

        $this->checkAndUpdateCmd('hp_status',       $isOn);
        $this->checkAndUpdateCmd('hp_state',        $stateStr);
        $this->checkAndUpdateCmd('hp_mode',         $modeStr);
        $this->checkAndUpdateCmd('hp_preset',       $presetStr);
        if ($targetTemp !== null)  $this->checkAndUpdateCmd('hp_target_temp',  $targetTemp);
        if ($currentTemp !== null) $this->checkAndUpdateCmd('hp_current_temp', round($currentTemp, 1));
    }

    private function applyLightState($device, $components) {
        $comp11 = $components[self::COMP_LIGHT_ONOFF]       ?? $components['11'] ?? [];
        $comp17 = $components[self::COMP_LIGHT_BRIGHTNESS]  ?? $components['17'] ?? [];

        $isOn       = (int)($comp11['reportedValue'] ?? $device['is_running'] ?? 0);
        $brightness = $comp17['reportedValue'] ?? null;

        $this->checkAndUpdateCmd('light_status',     $isOn);
        if ($brightness !== null) {
            $this->checkAndUpdateCmd('light_brightness', $brightness);
        }
    }

    private function applyChlorinatorState($device, $components) {
        $comp9 = $components['9'] ?? $components[self::COMP_PUMP_ONOFF] ?? [];
        $isOn  = (int)($comp9['reportedValue'] ?? $device['is_running'] ?? 0);
        $this->checkAndUpdateCmd('chlor_status', $isOn);
    }

    // ------ Exécution des commandes ------

    public function execute($_options = []) {
        $cmd = $this;
        if (!($cmd instanceof fluidrapoolCmd)) return;

        $logicalId = $cmd->getLogicalId();
        $eqLogic   = $cmd->getEqLogic();
        $deviceId  = $eqLogic->getConfiguration('device_id', '');
        $deviceType = $eqLogic->getConfiguration('device_type', '');

        $args = ['--device-id', escapeshellarg($deviceId)];

        switch ($logicalId) {
            // Pompe
            case 'pump_on':
                self::callApi(array_merge($args, ['--action', 'pump_on']));
                break;
            case 'pump_off':
                self::callApi(array_merge($args, ['--action', 'pump_off']));
                break;
            case 'pump_speed_low':
                self::callApi(array_merge($args, ['--action', 'set_pump_speed', '--value', '0']));
                break;
            case 'pump_speed_med':
                self::callApi(array_merge($args, ['--action', 'set_pump_speed', '--value', '1']));
                break;
            case 'pump_speed_high':
                self::callApi(array_merge($args, ['--action', 'set_pump_speed', '--value', '2']));
                break;
            case 'pump_auto_on':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '10', '--value', '1']));
                break;
            case 'pump_auto_off':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '10', '--value', '0']));
                break;

            // PAC
            case 'hp_on':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '13', '--value', '1']));
                break;
            case 'hp_off':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '13', '--value', '0']));
                break;
            case 'hp_set_temp':
                $temp   = (float)($_options['slider'] ?? 25);
                $raw    = (int)($temp * 10);
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '15', '--value', (string)$raw]));
                break;
            case 'hp_mode_heat':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '16', '--value', '0']));
                break;
            case 'hp_mode_cool':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '16', '--value', '1']));
                break;
            case 'hp_mode_auto':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '16', '--value', '2']));
                break;
            case 'hp_preset_silence':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '17', '--value', '0']));
                break;
            case 'hp_preset_smart':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '17', '--value', '1']));
                break;
            case 'hp_preset_boost':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '17', '--value', '2']));
                break;

            // Lumière
            case 'light_on':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '11', '--value', '1']));
                break;
            case 'light_off':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '11', '--value', '0']));
                break;
            case 'light_set_brightness':
                $bri = (int)($_options['slider'] ?? 50);
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '17', '--value', (string)$bri]));
                break;

            // Électrolyseur
            case 'chlor_on':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '9', '--value', '1']));
                break;
            case 'chlor_off':
                self::callApi(array_merge($args, ['--action', 'set_component', '--component', '9', '--value', '0']));
                break;

            // Rafraîchir
            case 'pump_refresh':
            case 'hp_refresh':
            case 'light_refresh':
            case 'chlor_refresh':
            case 'device_refresh':
            case 'pool_refresh':
                $eqLogic->refreshData();
                break;
        }

        // Mise à jour de l'état après action
        if (!in_array($logicalId, ['pump_refresh', 'hp_refresh', 'light_refresh', 'chlor_refresh', 'device_refresh', 'pool_refresh'])) {
            sleep(2);
            $eqLogic->refreshData();
        }
    }

    public function postSave() {
        // Rien de spécial
    }

    public function preRemove() {
        // Nettoyage éventuel
    }
}


class fluidrapoolCmd extends cmd {

    public function execute($_options = []) {
        if ($this->getType() !== 'action') return;
        $eqLogic = $this->getEqLogic();
        $eqLogic->execute($_options);
    }
}
