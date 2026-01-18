<?php
/**
 * Plugin Name: ImpactShop Identity Panel
 * Description: Shortcode for pseudo ID display, PIN request, and profile restore.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route(
        'impact/v1',
        '/identity/profile',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'impactshop_identity_profile_get',
            'permission_callback' => '__return_true',
        ]
    );

    register_rest_route(
        'impact/v1',
        '/identity/profile',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'impactshop_identity_profile_update',
            'permission_callback' => '__return_true',
        ]
    );
});

add_shortcode('impactshop_identity_panel', 'impactshop_identity_panel_shortcode');

/**
 * Shortcode UI for pseudo ID and PIN restore.
 *
 * @return string
 */
function impactshop_identity_panel_shortcode(): string
{
    $rest_base = esc_url_raw(rest_url('impact/v1'));
    $panel_id = 'impactshop-identity-panel-' . wp_generate_password(6, false, false);

    $html = '<div class="impactshop-identity-panel" id="' . esc_attr($panel_id) . '" ';
    $html .= 'data-rest-base="' . esc_attr($rest_base) . '">';
    $html .= '<div class="impactshop-identity-card">';
    $html .= '<h3>Azonosítód</h3>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<code class="impactshop-identity-value" data-role="pseudo-display">—</code>';
    $html .= '<button type="button" data-role="copy-pseudo">Másolás</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-identity-hint">Ezzel kapcsoljuk össze az adományt és a jutalmakat.</p>';
    $html .= '<label>Azonosító (ha új eszközön állítod vissza)</label>';
    $html .= '<input type="text" data-role="pseudo-input" maxlength="12" placeholder="pl. ab12cd34ef56" />';
    $html .= '<label>Becenév (opcionális)</label>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<input type="text" data-role="nickname-input" maxlength="32" placeholder="pl. Anna" />';
    $html .= '<button type="button" data-role="save-nickname">Mentés</button>';
    $html .= '</div>';

    $html .= '<hr />';
    $html .= '<h4>Helyreállítási PIN kérése</h4>';
    $html .= '<label>Csatorna</label>';
    $html .= '<select data-role="delivery-channel">';
    $html .= '<option value="">Válassz…</option>';
    $html .= '<option value="sms">SMS</option>';
    $html .= '<option value="email">Email</option>';
    $html .= '<option value="qr">QR</option>';
    $html .= '</select>';
    $html .= '<label>Cél (telefonszám vagy email)</label>';
    $html .= '<input type="text" data-role="delivery-target" placeholder="+36..." />';
    $html .= '<button type="button" data-role="request-pin">PIN kérése</button>';

    $html .= '<hr />';
    $html .= '<h4>Profil helyreállítása</h4>';
    $html .= '<label>PIN</label>';
    $html .= '<div class="impactshop-identity-row">';
    $html .= '<input type="text" data-role="pin-input" maxlength="6" placeholder="6 számjegy" />';
    $html .= '<button type="button" data-role="restore-profile">Profil helyreállítása</button>';
    $html .= '</div>';

    $html .= '<p class="impactshop-identity-status" data-role="status"></p>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<style>
      .impactshop-identity-panel { max-width: 520px; margin: 16px auto; font-family: inherit; }
      .impactshop-identity-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #fff; }
      .impactshop-identity-row { display: flex; gap: 8px; align-items: center; }
      .impactshop-identity-row input { flex: 1; }
      .impactshop-identity-card label { display: block; margin-top: 10px; font-weight: 600; }
      .impactshop-identity-card input, .impactshop-identity-card select { width: 100%; padding: 8px; border: 1px solid #cbd5f5; border-radius: 8px; }
      .impactshop-identity-card button { padding: 8px 12px; border-radius: 8px; border: 1px solid #1f2937; background: #111827; color: #fff; cursor: pointer; }
      .impactshop-identity-card code { background: #f3f4f6; padding: 6px 8px; border-radius: 6px; }
      .impactshop-identity-card hr { margin: 16px 0; border: none; border-top: 1px solid #e5e7eb; }
      .impactshop-identity-status { margin-top: 12px; color: #111827; }
      .impactshop-identity-hint { color: #6b7280; margin-top: 6px; }
    </style>';

    $html .= '<script>
      (function(){
        const root = document.getElementById(' . wp_json_encode($panel_id) . ');
        if (!root) return;
        const restBase = root.getAttribute("data-rest-base") || "";
        const statusEl = root.querySelector("[data-role=status]");
        const pseudoDisplay = root.querySelector("[data-role=pseudo-display]");
        const pseudoInput = root.querySelector("[data-role=pseudo-input]");
        const nicknameInput = root.querySelector("[data-role=nickname-input]");
        const channelSelect = root.querySelector("[data-role=delivery-channel]");
        const targetInput = root.querySelector("[data-role=delivery-target]");
        const pinInput = root.querySelector("[data-role=pin-input]");

        function setStatus(msg, isError) {
          statusEl.textContent = msg;
          statusEl.style.color = isError ? "#b91c1c" : "#111827";
        }

        function getCookie(name) {
          const match = document.cookie.match(new RegExp("(^|; )" + name + "=([^;]*)"));
          return match ? decodeURIComponent(match[2]) : "";
        }

        function refreshPseudo() {
          const pseudo = getCookie("impactshop_pseudo_id");
          pseudoDisplay.textContent = pseudo || "—";
          if (!pseudoInput.value) {
            pseudoInput.value = pseudo || "";
          }
        }

        async function fetchProfile() {
          try {
            const res = await fetch(restBase + "/identity/profile", { credentials: "same-origin" });
            if (!res.ok) return;
            const data = await res.json();
            if (data && data.nickname && !nicknameInput.value) {
              nicknameInput.value = data.nickname;
            }
          } catch (e) {
            // silent
          }
        }

        function isPseudoValid(value) {
          return /^[a-z0-9]{10,12}$/.test(value);
        }

        root.querySelector("[data-role=copy-pseudo]").addEventListener("click", async function(){
          const value = pseudoDisplay.textContent.trim();
          if (!value || value === "—") {
            setStatus("Nincs aktív azonosító.", true);
            return;
          }
          try {
            await navigator.clipboard.writeText(value);
            setStatus("Azonosító másolva.");
          } catch (e) {
            setStatus("Másolás sikertelen.", true);
          }
        });

        root.querySelector("[data-role=request-pin]").addEventListener("click", async function(){
          const pseudo = (pseudoInput.value || "").trim();
          const channel = channelSelect.value;
          const target = (targetInput.value || "").trim();
          if (!isPseudoValid(pseudo)) {
            setStatus("Adj meg érvényes azonosítót.", true);
            return;
          }
          if (!channel) {
            setStatus("Válassz kézbesítési csatornát.", true);
            return;
          }
          if (channel !== "qr" && !target) {
            setStatus("Add meg a kézbesítési címet.", true);
            return;
          }
          setStatus("PIN kérés folyamatban…");
          const payload = {
            pseudo_id: pseudo,
            context: "impactshop",
            delivery: { channel: channel, target: target }
          };
          try {
            const res = await fetch(restBase + "/identity/pin/issue", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              credentials: "same-origin",
              body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok) {
              setStatus((data && data.message) ? data.message : "PIN kérés sikertelen.", true);
              return;
            }
            if (data && data.delivery && data.delivery.status === "test_bypassed" && data.delivery.pin) {
              setStatus("PIN (teszt mód): " + data.delivery.pin);
              return;
            }
            setStatus("PIN kiküldve. Érvényesség: " + data.pin_ttl_sec + " mp.");
          } catch (e) {
            setStatus("PIN kérés hiba.", true);
          }
        });

        root.querySelector("[data-role=restore-profile]").addEventListener("click", async function(){
          const pseudo = (pseudoInput.value || "").trim();
          const pin = (pinInput.value || "").trim();
          if (!isPseudoValid(pseudo)) {
            setStatus("Adj meg érvényes azonosítót.", true);
            return;
          }
          if (!/^[0-9]{6}$/.test(pin)) {
            setStatus("Adj meg 6 számjegyű PIN-t.", true);
            return;
          }
          setStatus("Ellenőrzés…");
          try {
            const res = await fetch(restBase + "/identity/pin/verify", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              credentials: "same-origin",
              body: JSON.stringify({ pseudo_id: pseudo, pin: pin })
            });
            const data = await res.json();
            if (!res.ok) {
              setStatus((data && data.message) ? data.message : "PIN ellenőrzés sikertelen.", true);
              return;
            }
            setStatus("Profil helyreállítva.");
            setTimeout(refreshPseudo, 300);
          } catch (e) {
            setStatus("PIN ellenőrzés hiba.", true);
          }
        });

        root.querySelector("[data-role=save-nickname]").addEventListener("click", async function(){
          const pseudo = (pseudoInput.value || "").trim();
          const nickname = (nicknameInput.value || "").trim();
          if (!isPseudoValid(pseudo)) {
            setStatus("Adj meg érvényes azonosítót.", true);
            return;
          }
          setStatus("Becenév mentése…");
          try {
            const res = await fetch(restBase + "/identity/profile", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              credentials: "same-origin",
              body: JSON.stringify({ pseudo_id: pseudo, nickname: nickname })
            });
            const data = await res.json();
            if (!res.ok) {
              setStatus((data && data.message) ? data.message : "Becenév mentése sikertelen.", true);
              return;
            }
            setStatus("Becenév mentve.");
          } catch (e) {
            setStatus("Becenév mentése hiba.", true);
          }
        });

        refreshPseudo();
        fetchProfile();
      })();
    </script>';

    return $html;
}

/**
 * Get the current profile for the pseudo ID cookie.
 *
 * @return WP_REST_Response
 */
function impactshop_identity_profile_get(): WP_REST_Response
{
    $pseudo_id = impactshop_identity_profile_cookie();
    if ($pseudo_id === '') {
        return new WP_REST_Response(['pseudo_id' => null, 'nickname' => null], 200);
    }

    $nickname = impactshop_identity_profile_load($pseudo_id);
    return new WP_REST_Response(
        [
            'pseudo_id' => $pseudo_id,
            'nickname'  => $nickname,
        ],
        200
    );
}

/**
 * Update nickname for current pseudo ID.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function impactshop_identity_profile_update(WP_REST_Request $request): WP_REST_Response
{
    $params = (array)$request->get_json_params();
    $pseudo_id = isset($params['pseudo_id']) ? (string)$params['pseudo_id'] : '';
    $nickname = isset($params['nickname']) ? (string)$params['nickname'] : '';

    if (!impactshop_identity_profile_valid_pseudo($pseudo_id)) {
        return new WP_REST_Response(['message' => 'Érvénytelen azonosító.'], 400);
    }

    $cookie_pseudo = impactshop_identity_profile_cookie();
    if ($cookie_pseudo === '' || $cookie_pseudo !== $pseudo_id) {
        return new WP_REST_Response(['message' => 'Azonosító nem egyezik a böngésző cookie-val.'], 403);
    }

    $nickname = sanitize_text_field($nickname);
    $nickname = trim($nickname);
    if ($nickname !== '' && !impactshop_identity_profile_valid_nickname($nickname)) {
        return new WP_REST_Response(['message' => 'Érvénytelen becenév.'], 400);
    }

    impactshop_identity_profile_store($pseudo_id, $nickname);
    return new WP_REST_Response(['status' => 'ok', 'nickname' => $nickname], 200);
}

/**
 * Read pseudo ID from cookie.
 *
 * @return string
 */
function impactshop_identity_profile_cookie(): string
{
    if (empty($_COOKIE['impactshop_pseudo_id']) || !is_string($_COOKIE['impactshop_pseudo_id'])) {
        return '';
    }
    return sanitize_text_field(wp_unslash($_COOKIE['impactshop_pseudo_id']));
}

/**
 * Validate pseudo ID format.
 *
 * @param string $pseudo_id Pseudo ID to validate.
 * @return bool
 */
function impactshop_identity_profile_valid_pseudo(string $pseudo_id): bool
{
    if (function_exists('impactshop_pin_valid_pseudo')) {
        return impactshop_pin_valid_pseudo($pseudo_id);
    }
    return (bool)preg_match('/^[a-z0-9]{10,12}$/', $pseudo_id);
}

/**
 * Validate nickname format.
 *
 * @param string $nickname Nickname to validate.
 * @return bool
 */
function impactshop_identity_profile_valid_nickname(string $nickname): bool
{
    $length = function_exists('mb_strlen') ? mb_strlen($nickname) : strlen($nickname);
    if ($length < 2 || $length > 32) {
        return false;
    }
    return (bool)preg_match('/^[\p{L}0-9 _.\-]+$/u', $nickname);
}

/**
 * Load nickname for pseudo ID.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return string|null
 */
function impactshop_identity_profile_load(string $pseudo_id): ?string
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $value = get_option($key, null);
    if (!is_array($value)) {
        return null;
    }
    return isset($value['nickname']) && is_string($value['nickname']) ? $value['nickname'] : null;
}

/**
 * Store nickname for pseudo ID.
 *
 * @param string $pseudo_id Pseudo ID.
 * @param string $nickname Nickname (can be empty to clear).
 * @return void
 */
function impactshop_identity_profile_store(string $pseudo_id, string $nickname): void
{
    $key = impactshop_identity_profile_option_key($pseudo_id);
    $payload = [
        'nickname'   => $nickname,
        'updated_at' => current_time('mysql', 1),
    ];

    $existing = get_option($key, null);
    if ($existing === null) {
        add_option($key, $payload, '', 'no');
        return;
    }

    update_option($key, $payload, false);
}

/**
 * Build option key for pseudo profile.
 *
 * @param string $pseudo_id Pseudo ID.
 * @return string
 */
function impactshop_identity_profile_option_key(string $pseudo_id): string
{
    $hash = hash_hmac('sha256', $pseudo_id, wp_salt('impactshop_profile'));
    return 'impactshop_pseudo_profile_' . $hash;
}
