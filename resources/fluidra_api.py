#!/usr/bin/env python3
"""
Fluidra Pool API client pour Jeedom.

Usage:
    python3 fluidra_api.py --email user@example.com --token-file /tmp/token.json --action get_all
    python3 fluidra_api.py --email user@example.com --token-file /tmp/token.json \
        --action set_component --device-id XXXX --component 13 --value 1

Le mot de passe est lu depuis la variable d'environnement FLUIDRA_PASSWORD.
Il n'est jamais passé en argument de ligne de commande (visible dans `ps`).
"""

import argparse
import json
import logging
import os
import sys
import time
from urllib.parse import quote

try:
    import requests
except ImportError:
    print(json.dumps({"error": "Module 'requests' manquant. Lancez: pip3 install requests"}))
    sys.exit(1)

# ---------------------------------------------------------------------------
# Constantes API
# ---------------------------------------------------------------------------
FLUIDRA_BASE = "https://api.fluidra-emea.com"
COGNITO_URL  = "https://cognito-idp.eu-west-1.amazonaws.com/"
CLIENT_ID    = "g3njunelkcbtefosqm9bdhhq1"
USER_AGENT   = (
    "com.fluidra.iaqualinkplus/1741857021 "
    "(Linux; U; Android 14; fr_FR; MI PAD 4; Build/UQ1A.240205.004; Cronet/140.0.7289.0)"
)
REQUEST_TIMEOUT = 30

logging.basicConfig(level=logging.WARNING, format="%(levelname)s %(message)s")
log = logging.getLogger("fluidra_api")


# ---------------------------------------------------------------------------
# Gestion des tokens (cache fichier)
# ---------------------------------------------------------------------------

def load_token_cache(token_file: str) -> dict:
    try:
        if os.path.exists(token_file):
            with open(token_file, "r") as f:
                return json.load(f)
    except Exception:
        pass
    return {}


def save_token_cache(token_file: str, cache: dict) -> None:
    try:
        os.makedirs(os.path.dirname(token_file), exist_ok=True)
        with open(token_file, "w") as f:
            json.dump(cache, f)
        os.chmod(token_file, 0o600)
    except Exception as e:
        log.warning("Impossible de sauvegarder le cache token : %s", e)


def is_token_expired(cache: dict) -> bool:
    expires_at = cache.get("expires_at", 0)
    return time.time() >= expires_at - 60


# ---------------------------------------------------------------------------
# Authentification Cognito
# ---------------------------------------------------------------------------

def cognito_auth(email: str, password: str) -> dict:
    """Authentification initiale email/password → retourne le cache token."""
    payload = {
        "AuthFlow": "USER_PASSWORD_AUTH",
        "ClientId": CLIENT_ID,
        "AuthParameters": {"USERNAME": email, "PASSWORD": password},
    }
    headers = {
        "Content-Type": "application/x-amz-json-1.1; charset=utf-8",
        "X-Amz-Target": "AWSCognitoIdentityProviderService.InitiateAuth",
        "User-Agent": USER_AGENT,
    }
    resp = requests.post(COGNITO_URL, json=payload, headers=headers, timeout=REQUEST_TIMEOUT)
    data = resp.json()

    if resp.status_code != 200:
        raise RuntimeError(f"Auth Cognito échouée ({resp.status_code}) : {data.get('message', data)}")

    challenge = data.get("ChallengeName", "")
    if challenge in ("SOFTWARE_TOKEN_MFA", "SMS_MFA"):
        raise RuntimeError(f"MFA requis ({challenge}) — non supporté en mode automatique")
    if challenge:
        raise RuntimeError(f"Challenge Cognito inattendu : {challenge}")

    result = data.get("AuthenticationResult", {})
    if not result.get("AccessToken"):
        raise RuntimeError("Pas de token d'accès dans la réponse Cognito")

    expires_in = result.get("ExpiresIn", 3600)
    return {
        "access_token":   result["AccessToken"],
        "id_token":       result.get("IdToken"),
        "refresh_token":  result.get("RefreshToken"),
        "expires_at":     int(time.time()) + expires_in,
    }


def cognito_refresh(refresh_token: str) -> dict | None:
    """Renouvelle l'access token via le refresh token. Retourne None si échec."""
    payload = {
        "AuthFlow": "REFRESH_TOKEN_AUTH",
        "ClientId": CLIENT_ID,
        "AuthParameters": {"REFRESH_TOKEN": refresh_token},
    }
    headers = {
        "Content-Type": "application/x-amz-json-1.1; charset=utf-8",
        "X-Amz-Target": "AWSCognitoIdentityProviderService.InitiateAuth",
        "User-Agent": USER_AGENT,
    }
    try:
        resp = requests.post(COGNITO_URL, json=payload, headers=headers, timeout=REQUEST_TIMEOUT)
        if resp.status_code == 200:
            result = resp.json().get("AuthenticationResult", {})
            if result.get("AccessToken"):
                expires_in = result.get("ExpiresIn", 3600)
                return {
                    "access_token":  result["AccessToken"],
                    "id_token":      result.get("IdToken"),
                    "refresh_token": refresh_token,
                    "expires_at":    int(time.time()) + expires_in,
                }
    except Exception as e:
        log.warning("Refresh token échoué : %s", e)
    return None


def ensure_valid_token(email: str, password: str, token_file: str) -> dict:
    """Retourne un cache token valide, en rafraîchissant ou ré-authentifiant si nécessaire."""
    cache = load_token_cache(token_file)

    if cache.get("access_token") and not is_token_expired(cache):
        return cache

    if cache.get("refresh_token"):
        refreshed = cognito_refresh(cache["refresh_token"])
        if refreshed:
            save_token_cache(token_file, refreshed)
            return refreshed

    if not password:
        raise RuntimeError("Mot de passe requis (variable FLUIDRA_PASSWORD) et refresh token invalide")

    new_cache = cognito_auth(email, password)
    save_token_cache(token_file, new_cache)
    return new_cache


# ---------------------------------------------------------------------------
# Appels API Fluidra
# ---------------------------------------------------------------------------

def api_headers(cache: dict) -> dict:
    return {
        "Authorization": f"Bearer {cache['access_token']}",
        "Content-Type":  "application/json",
        "Accept":        "application/json",
        "User-Agent":    USER_AGENT,
    }


def api_get(path: str, cache: dict, params: dict = None) -> dict | list:
    url  = f"{FLUIDRA_BASE}{path}"
    resp = requests.get(url, headers=api_headers(cache), params=params, timeout=REQUEST_TIMEOUT)
    resp.raise_for_status()
    return resp.json()


def api_put(path: str, payload: dict, cache: dict) -> dict:
    url  = f"{FLUIDRA_BASE}{path}"
    resp = requests.put(url, json=payload, headers=api_headers(cache), timeout=REQUEST_TIMEOUT)
    resp.raise_for_status()
    return resp.json() if resp.text else {}


# ---------------------------------------------------------------------------
# Récupération des données piscine
# ---------------------------------------------------------------------------

def get_user_pools(cache: dict) -> list:
    data = api_get("/generic/users/me/pools", cache)
    if isinstance(data, list):
        return data
    return data.get("pools", [])


def get_devices_for_pool(pool_id: str, cache: dict) -> list:
    data = api_get("/generic/devices", cache, params={"poolId": pool_id, "format": "tree"})
    if isinstance(data, list):
        raw_devices = data
    else:
        raw_devices = data.get("devices", [])

    devices = []
    seen = {}

    def add_device(d):
        did  = d.get("id") or d.get("device_id")
        key  = str(did) if did else f"__noid_{len(seen)}"
        conn = d.get("type", "unknown")
        built = {
            "device_id":       did,
            "name":            d.get("info", {}).get("name", f"Device {did}"),
            "type":            _classify_device(d.get("info", {}).get("family", ""), d.get("info", {}).get("name", "")),
            "family":          d.get("info", {}).get("family", ""),
            "connection_type": conn,
            "online":          conn == "connected",
            "components":      {},
        }
        if key not in seen or (built["connection_type"] == "connected" and seen[key]["connection_type"] != "connected"):
            seen[key] = built

    for device in raw_devices:
        family = device.get("info", {}).get("family", "")
        is_bridge = "bridge" in family.lower() or bool(device.get("devices"))
        if is_bridge:
            for child in device.get("devices") or []:
                add_device(child)
        else:
            add_device(device)

    return list(seen.values())


def _classify_device(family: str, name: str) -> str:
    family_l = family.lower()
    name_l   = name.lower()
    if any(k in family_l for k in ["pump", "pompe"]):       return "pump"
    if any(k in family_l for k in ["heat", "pac", "therm"]): return "heat_pump"
    if any(k in family_l for k in ["chlor", "electro", "sel"]): return "chlorinator"
    if any(k in family_l for k in ["light", "lumiere", "lumi"]): return "light"
    if any(k in family_l for k in ["sensor", "probe", "blueco"]): return "sensor"
    if any(k in name_l   for k in ["pump", "pompe"]):       return "pump"
    if any(k in name_l   for k in ["heat", "pac", "therm"]): return "heat_pump"
    if any(k in name_l   for k in ["chlor", "electro"]):     return "chlorinator"
    if any(k in name_l   for k in ["light", "lumi"]):        return "light"
    return "unknown"


def get_component_state(device_id: str, component_id: int, cache: dict) -> dict:
    path = f"/generic/devices/{quote(str(device_id), safe='')}/components/{component_id}"
    try:
        return api_get(path, cache, params={"deviceType": "connected"})
    except Exception:
        return {}


def get_device_components(device_id: str, cache: dict) -> dict:
    """Récupère les composants clés d'un device (PAC, pompe)."""
    important_components = [9, 10, 11, 13, 14, 15, 16, 17, 21, 61]
    components = {}
    for comp_id in important_components:
        state = get_component_state(device_id, comp_id, cache)
        if state:
            components[str(comp_id)] = state
    return components


def get_water_quality(pool_id: str, cache: dict) -> dict:
    path   = f"/generic/pools/{quote(str(pool_id), safe='')}/assistant/algorithms/telemetryWaterQuality/jobs"
    try:
        data   = api_get(path, cache, params={"pageSize": 1})
        jobs   = data.get("jobs", data.get("items", []))
        if jobs:
            latest = jobs[0]
            result = latest.get("result", latest.get("output", {}))
            if isinstance(result, str):
                result = json.loads(result)
            return result or {}
    except Exception as e:
        log.debug("Qualité eau indisponible pour pool %s : %s", pool_id, e)
    return {}


def get_pool_status(pool_id: str, cache: dict) -> dict:
    try:
        return api_get(f"/generic/pools/{quote(str(pool_id), safe='')}/status", cache)
    except Exception:
        return {}


def get_pool_details(pool_id: str, cache: dict) -> dict:
    try:
        return api_get(f"/generic/pools/{quote(str(pool_id), safe='')}", cache)
    except Exception:
        return {}


def set_component(device_id: str, component_id: int, value, cache: dict) -> bool:
    path    = f"/generic/devices/{quote(str(device_id), safe='')}/components/{component_id}?deviceType=connected"
    payload = {"desiredValue": value}
    try:
        api_put(path, payload, cache)
        return True
    except Exception as e:
        log.warning("Erreur set_component(%s, %s, %s) : %s", device_id, component_id, value, e)
        return False


# ---------------------------------------------------------------------------
# Actions de haut niveau
# ---------------------------------------------------------------------------

def action_get_all(cache: dict) -> dict:
    pools_raw  = get_user_pools(cache)
    result     = {"pools": []}

    for pool_raw in pools_raw:
        pool_id   = pool_raw.get("id")
        pool_name = pool_raw.get("name", f"Piscine {pool_id}")

        devices     = get_devices_for_pool(pool_id, cache)
        status_data = get_pool_status(pool_id, cache)
        pool_detail = get_pool_details(pool_id, cache)
        water_q     = get_water_quality(pool_id, cache)

        # Enrichissement des devices avec composants
        for device in devices:
            did = device["device_id"]
            if did and device["online"]:
                device["components"] = get_device_components(did, cache)

        pool_entry = {
            "id":          pool_id,
            "name":        pool_name,
            "state":       pool_raw.get("state", pool_detail.get("state", "unknown")),
            "geolocation": pool_raw.get("geolocation", pool_detail.get("geolocation", {})),
            "characteristics": pool_raw.get("characteristics", pool_detail.get("characteristics", {})),
            "disinfection":    pool_raw.get("disinfection", pool_detail.get("disinfection", {})),
            "status_data": status_data,
            "water_quality":   water_q,
            "devices":         devices,
        }
        result["pools"].append(pool_entry)

    return result


def action_pump_on(device_id: str, cache: dict) -> dict:
    ok = set_component(device_id, 9, 1, cache)
    return {"success": ok}


def action_pump_off(device_id: str, cache: dict) -> dict:
    ok = set_component(device_id, 9, 0, cache)
    return {"success": ok}


def action_set_pump_speed(device_id: str, value: int, cache: dict) -> dict:
    # S'assurer que la pompe est allumée
    set_component(device_id, 9, 1, cache)
    time.sleep(1)
    ok = set_component(device_id, 11, value, cache)
    return {"success": ok}


def action_set_component(device_id: str, component_id: int, value, cache: dict) -> dict:
    ok = set_component(device_id, component_id, value, cache)
    return {"success": ok}


# ---------------------------------------------------------------------------
# Point d'entrée
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(description="Fluidra Pool API pour Jeedom")
    parser.add_argument("--email",      required=True,  help="Email du compte Fluidra")
    parser.add_argument("--token-file", required=True,  help="Chemin du fichier de cache token")
    parser.add_argument("--action",     required=True,
                        choices=["get_all", "pump_on", "pump_off", "set_pump_speed", "set_component"])
    parser.add_argument("--device-id",  default="",     help="ID du device cible")
    parser.add_argument("--component",  type=int,       help="ID du composant (set_component)")
    parser.add_argument("--value",      default="",     help="Valeur à appliquer")
    args = parser.parse_args()

    password = os.environ.get("FLUIDRA_PASSWORD", "")

    try:
        cache = ensure_valid_token(args.email, password, args.token_file)
    except Exception as e:
        print(json.dumps({"error": f"Authentification échouée : {e}"}))
        sys.exit(1)

    try:
        if args.action == "get_all":
            result = action_get_all(cache)

        elif args.action == "pump_on":
            result = action_pump_on(args.device_id, cache)

        elif args.action == "pump_off":
            result = action_pump_off(args.device_id, cache)

        elif args.action == "set_pump_speed":
            result = action_set_pump_speed(args.device_id, int(args.value), cache)

        elif args.action == "set_component":
            if args.component is None:
                raise ValueError("--component requis pour set_component")
            raw_value = args.value
            try:
                parsed = json.loads(raw_value)
            except (json.JSONDecodeError, ValueError):
                parsed = raw_value
                try:
                    parsed = int(raw_value)
                except ValueError:
                    try:
                        parsed = float(raw_value)
                    except ValueError:
                        pass
            result = action_set_component(args.device_id, args.component, parsed, cache)

        else:
            result = {"error": f"Action inconnue : {args.action}"}

    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
