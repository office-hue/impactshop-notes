<?php
/**
 * Plugin Name: ImpactShop Impi Chat Widget
 * Description: Kisméretű, elszigetelt Impi chat dokk az ImpactShop oldalra.
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('IMPACTSHOP_IMPI_WIDGET_PAGE_ID')) {
    define('IMPACTSHOP_IMPI_WIDGET_PAGE_ID', 16348); // ImpactShop landing
}

// Alapból kikapcsolva, amíg az Impi agent nincs beüzemelve.
if (!defined('IMPACTSHOP_IMPI_WIDGET_ENABLED')) {
    define('IMPACTSHOP_IMPI_WIDGET_ENABLED', true);
}

// Hard kill switch: amíg az Impi nincs beüzemelve, semmilyen feltétellel ne jelenjen meg.
if (!defined('IMPACTSHOP_IMPI_FORCE_DISABLE')) {
    define('IMPACTSHOP_IMPI_FORCE_DISABLE', false);
}

/**
 * Alapértelmezett médiaforrások (videó + fallback kép).
 *
 * MP4/WEBM URL-eket env/const alapon felül lehet írni:
 *   define('IMPACTSHOP_IMPI_VIDEO_MP4', 'https://app.sharity.hu/.../impi-loop.mp4');
 *   define('IMPACTSHOP_IMPI_VIDEO_WEBM', 'https://app.sharity.hu/.../impi-loop.webm');
 */
function impactshop_impi_media_sources() {
    $defaults = [
        'mp4'  => defined('IMPACTSHOP_IMPI_VIDEO_MP4') ? IMPACTSHOP_IMPI_VIDEO_MP4 : 'https://app.sharity.hu/wp-content/uploads/2025/12/Impi-Loop_Animation_Request.mp4',
        'webm' => defined('IMPACTSHOP_IMPI_VIDEO_WEBM') ? IMPACTSHOP_IMPI_VIDEO_WEBM : '',
        // Egyszerű SVG fallback, ha nincs videó/PNG feltöltve.
        'fallback' => 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><defs><linearGradient id="g" x1="0%" x2="100%" y1="0%" y2="100%"><stop stop-color="%230acbf7" offset="0%"/><stop stop-color="%237c3aed" offset="100%"/></linearGradient></defs><rect width="120" height="120" rx="60" fill="url(%23g)"/><text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" fill="%23f8fafc" font-family="Arial, sans-serif" font-size="34" font-weight="700">Impi</text></svg>'),
    ];

    return apply_filters('impactshop_impi_media_sources', $defaults);
}

function impactshop_impi_should_render() {
    if (IMPACTSHOP_IMPI_FORCE_DISABLE) {
        return false;
    }
    if (!IMPACTSHOP_IMPI_WIDGET_ENABLED || is_admin()) {
        return false;
    }
    if (function_exists('is_page') && is_page(IMPACTSHOP_IMPI_WIDGET_PAGE_ID)) {
        return true;
    }
    // Engedélyezhető más oldalakra filterrel.
    return apply_filters('impactshop_impi_widget_force_render', false);
}

function impactshop_render_impi_chat() {
    if (!impactshop_impi_should_render()) {
        return;
    }

    $media = impactshop_impi_media_sources();
    $has_video = !empty($media['mp4']) || !empty($media['webm']);
    $position = (defined('IMPACTSHOP_IMPI_POSITION') && in_array(IMPACTSHOP_IMPI_POSITION, ['left','right'], true))
        ? IMPACTSHOP_IMPI_POSITION
        : 'left'; // Mozgatva, hogy ne ütközzön az akadálymentességi overlayjel.
    ?>
    <style>
      /* Izolált wrapper: nem használjuk a theme .chat-dock osztályát */
      .impi-chat-dock {
        position: fixed;
        right: 20px;
        left: auto;
        bottom: 140px; /* elemelve a sticky sávoktól, hogy ne takarja */
        z-index: 9999;
        display: flex;
        flex-direction: row;
        align-items: flex-end;
        gap: 10px;
        max-width: 300px;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        box-sizing: border-box;
      }
      .impi-chat-dock * { box-sizing: border-box; }
      .impi-chat-dock .impi-toggle { cursor: pointer; }

      /* Impi avatar – fix méret, saját specifitás + !important a theme override-ok ellen */
      .impi-chat-dock .impi-avatar {
        flex: 0 0 56px;
        width: 56px;
        height: 56px;
        border-radius: 999px;
        overflow: hidden;
        background: #e0f7fc;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .impi-chat-dock .impi-avatar video,
      .impi-chat-dock .impi-avatar img {
        width: 100% !important;
        height: 100% !important;
        max-width: 100% !important;
        max-height: 100% !important;
        object-fit: contain;
        display: block;
      }

      /* Szövegbuborék */
      .impi-chat-dock .impi-bubble {
        flex: 1 1 auto;
        min-width: 0;
        max-width: 220px;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.10);
        padding: 10px 12px;
        font-size: 13px;
        line-height: 1.4;
        color: #1f2933;
      }
      .impi-chat-dock .impi-bubble-title {
        font-weight: 700;
        color: #0c9fbf;
        margin-bottom: 4px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
      }
      .impi-chat-dock .impi-badge {
        display: inline-flex;
        padding: 4px 8px;
        border-radius: 999px;
        background: linear-gradient(135deg, #38bdf8, #7c3aed);
        color: #0b1220;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
      }
      .impi-chat-dock .impi-bubble-text { margin: 0; }

      @media (max-width: 600px) {
        .impi-chat-dock {
          right: 10px;
          left: auto;
          bottom: 110px; /* mobilon is feljebb húzva */
          gap: 6px;
          max-width: 100%;
        }
      .impi-chat-dock .impi-avatar {
        flex: 0 0 52px;
        width: 52px;
        height: 52px;
      }
      .impi-chat-dock .impi-bubble {
        max-width: 200px;
        font-size: 12.5px;
      }
      }

      .impi-chat-dock.impi-pos-left {
        left: 20px;
        right: auto;
      }
      @media (max-width: 600px) {
        .impi-chat-dock.impi-pos-left {
          left: 10px;
          right: auto;
        }
      }
    </style>

    <div class="impi-chat-dock <?php echo $position === 'left' ? 'impi-pos-left' : 'impi-pos-right'; ?>" role="complementary" aria-label="Impi AI chat">
      <div class="impi-avatar">
        <?php if ($has_video) : ?>
          <video autoplay loop muted playsinline preload="auto">
            <?php if (!empty($media['webm'])) : ?>
              <source src="<?php echo esc_url($media['webm']); ?>" type="video/webm" />
            <?php endif; ?>
            <?php if (!empty($media['mp4'])) : ?>
              <source src="<?php echo esc_url($media['mp4']); ?>" type="video/mp4" />
            <?php endif; ?>
          </video>
        <?php else : ?>
          <img src="<?php echo esc_url($media['fallback']); ?>" alt="Impi avatar" loading="lazy" decoding="async" />
        <?php endif; ?>
      </div>
      <div class="impi-bubble">
        <div class="impi-bubble-title">
          <span>Kérdezz Impitől</span>
          <span class="impi-badge">Beta</span>
        </div>
        <p class="impi-bubble-text">Kérdezz a Sharity adományozási lehetőségeiről, Impact Shopról és kampányokról! 💙</p>
      </div>
    </div>
    <style>
      .impi-chat-panel {
        position: fixed;
        right: 20px;
        left: auto;
        bottom: 90px;
        width: 280px;
        max-width: calc(100% - 32px);
        background: #0f172a;
        color: #f8fafc;
        border-radius: 16px;
        box-shadow: 0 18px 36px rgba(0,0,0,0.18);
        padding: 14px;
        z-index: 9999;
        display: none;
      }
      .impi-chat-panel.impi-pos-left { left: 20px; right: auto; }
      .impi-chat-panel h4 {
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 700;
      }
      .impi-chat-panel textarea {
        width: 100%;
        min-height: 72px;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.15);
        background: rgba(255,255,255,0.06);
        color: #f8fafc;
        padding: 10px;
        font-size: 14px;
        resize: vertical;
      }
      .impi-chat-panel textarea:focus { outline: 2px solid #38bdf8; }
      .impi-chat-panel .impi-actions {
        margin-top: 10px;
        display: flex;
        gap: 8px;
        align-items: center;
      }
      .impi-chat-panel button {
        background: linear-gradient(135deg, #38bdf8, #7c3aed);
        color: #0b1220;
        border: none;
        border-radius: 12px;
        padding: 10px 14px;
        font-weight: 700;
        cursor: pointer;
      }
      .impi-chat-panel button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }
      .impi-chat-panel .impi-status {
        font-size: 13px;
        color: #cbd5f5;
      }
      .impi-chat-panel .impi-response {
        margin-top: 12px;
        background: rgba(255,255,255,0.06);
        border-radius: 10px;
        padding: 10px;
        font-size: 14px;
        line-height: 1.5;
        max-height: 220px;
        overflow: auto;
        word-break: break-word;
      }
      .impi-chat-panel .impi-response a {
        color: #38bdf8;
        text-decoration: underline;
        word-break: break-word;
      }
      .impi-chat-panel .impi-response a:hover {
        text-decoration: none;
      }
    </style>
    <div class="impi-chat-panel <?php echo $position === 'left' ? 'impi-pos-left' : 'impi-pos-right'; ?>" role="dialog" aria-modal="false" aria-live="polite">
      <h4>Impi chat</h4>
      <textarea id="impiChatInput" placeholder="Írd ide a kérdésed..."></textarea>
      <div class="impi-actions">
        <button id="impiChatSend">Küldés</button>
        <span class="impi-status" id="impiChatStatus"></span>
      </div>
      <div class="impi-response" id="impiChatResponse" hidden></div>
    </div>
    <script>
      (function() {
        const dock = document.querySelector('.impi-chat-dock');
        const panel = document.querySelector('.impi-chat-panel');
        if (!dock || !panel) return;
        const input = panel.querySelector('#impiChatInput');
        const send = panel.querySelector('#impiChatSend');
        const status = panel.querySelector('#impiChatStatus');
        const resp = panel.querySelector('#impiChatResponse');
        let open = false;

        function toggle() {
          open = !open;
          panel.style.display = open ? 'block' : 'none';
          if (open && input) input.focus();
        }
        dock.addEventListener('click', toggle);

        async function doSend() {
          const msg = (input?.value || '').trim();
          if (!msg) return;
          try {
            fetch('/wp-json/impact/v1/event', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'include',
              body: JSON.stringify({
                event_type: 'impi_question',
                event_source: 'impi_chat',
                meta: {
                  question: msg.substring(0, 500)
                }
              })
            }).catch(function(){});
          } catch (e) {
            // ignore logging errors
          }
          send.disabled = true;
          status.textContent = 'Küldés...';
          resp.hidden = true;
          try {
            const res = await fetch('/ai-agent/api/v1/chat/impi', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ message: msg }),
            });
            if (!res.ok) {
              throw new Error('HTTP ' + res.status);
            }
            const data = await res.json();
            const raw = data?.impi?.summary || 'Válasz érkezett, de nincs megjeleníthető szöveg.';
            const normalized = raw.replace(/<([^>]+)>/g, '$1');
            const answer = normalized
              // Autolink: wrap sima http/https URL-t <a>-ba, levágva a mondatvégi írásjeleket
              .replace(/https?:\/\/[^\s<>()]+/g, url => {
                const clean = url.replace(/[),.;!?]+$/, '');
                const trailing = url.slice(clean.length);
                return `<a href=\"${clean}\" target=\"_blank\" rel=\"noopener noreferrer\">${clean}</a>${trailing}`;
              })
              // Sorvégek → <br> a jobb tördelésért
              .replace(/\\n/g, '<br>');
            resp.innerHTML = answer;
            resp.hidden = false;
            status.textContent = 'Kész';
            input.value = '';
          } catch (err) {
            status.textContent = 'Hiba: ' + (err?.message || 'ismeretlen');
          } finally {
            send.disabled = false;
          }
        }
        send?.addEventListener('click', doSend);
        input?.addEventListener('keydown', function(e) {
          if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            doSend();
          }
        });
      })();
    </script>
    <?php
}

add_action('wp_footer', 'impactshop_render_impi_chat', 99);
