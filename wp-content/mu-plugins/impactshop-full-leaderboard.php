<?php
/**
 * Plugin Name: ImpactShop Full Leaderboard
 * Description: Full NGO/shop leaderboard for the Impact Shop totals page.
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('impactshop_full_leaderboard_fetch')) {
    function impactshop_full_leaderboard_fetch(array $params): array
    {
        $qs = http_build_query($params);
        $url = home_url('/wp-json/impact/v1/leaderboard?' . $qs);
        $res = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($res)) {
            return [];
        }
        $code = wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 300) {
            return [];
        }
        $body = wp_remote_retrieve_body($res);
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }
}

if (!shortcode_exists('impact_full_leaderboard')) {
    add_shortcode('impact_full_leaderboard', function ($atts) {
        $a = shortcode_atts([
            'from'     => '2025-10-23',
            'to'       => '',
            'status'   => 'all',
            'currency' => 'HUF',
            'rate_huf' => '392',
            'limit'    => '0',
            'layout'   => 'rich',
        ], $atts, 'impact_full_leaderboard');

        $from = sanitize_text_field($a['from']);
        $to = sanitize_text_field($a['to']) ?: date('Y-m-d');
        $status = sanitize_text_field($a['status']);
        $currency = strtoupper(trim($a['currency'] ?: 'HUF'));
        $rate = max(1, (float)$a['rate_huf']);
        $limit = max(0, (int)$a['limit']);

        $ngo = impactshop_full_leaderboard_fetch([
            'tab' => 'ngo',
            'from' => $from,
            'to' => $to,
            'status' => $status,
        ]);
        $shop = impactshop_full_leaderboard_fetch([
            'tab' => 'shop',
            'from' => $from,
            'to' => $to,
            'status' => $status,
        ]);

        if ($limit > 0) {
            $ngo = array_slice($ngo, 0, $limit);
            $shop = array_slice($shop, 0, $limit);
        }

        $fmt = function ($value) use ($currency, $rate) {
            $value = (float)$value;
            if ($currency === 'HUF') {
                return number_format($value * $rate, 0, ',', ' ') . ' Ft';
            }
            return number_format($value, 2, ',', ' ') . ' €';
        };

        if (strtolower($a['layout']) === 'rich') {
            $total_eur = 0.0;
            $max_eur = 0.0;
            foreach ($ngo as $row) {
                $amt = (float)($row['amount'] ?? 0);
                $total_eur += $amt;
                if ($amt > $max_eur) {
                    $max_eur = $amt;
                }
            }
            $count = count($ngo);
            $fmt_huf = function ($value) use ($rate) {
                return number_format($value * $rate, 0, ',', ' ') . ' Ft';
            };
            $fmt_eur = function ($value) {
                return number_format($value, 2, ',', ' ') . ' €';
            };

            ob_start(); ?>
            <section class="impact-ngo-leaderboard" aria-labelledby="impact-ngo-title">
              <style>
                .impact-ngo-leaderboard {
                  font-family: "Inter", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
                  background: linear-gradient(180deg, #f8fafc 0%, #ffffff 55%, #e0f2fe 100%);
                  border: 1px solid rgba(15, 23, 42, 0.08);
                  border-radius: 28px;
                  padding: clamp(1.75rem, 2vw + 1.25rem, 3.5rem);
                  box-shadow: 0 44px 90px rgba(15, 23, 42, 0.12);
                }
                .impact-ngo-header {
                  text-align: center;
                  max-width: 60rem;
                  margin: 0 auto clamp(1.8rem, 3vw, 2.75rem);
                }
                .impact-ngo-eyebrow {
                  display: inline-flex;
                  align-items: center;
                  gap: 0.4rem;
                  padding: 0.3rem 0.9rem;
                  border-radius: 999px;
                  background: rgba(2, 132, 199, 0.12);
                  color: #0369a1;
                  letter-spacing: 0.18em;
                  text-transform: uppercase;
                  font-size: 0.78rem;
                  font-weight: 700;
                }
                .impact-ngo-header h2 {
                  margin: 0.65rem 0 0.45rem;
                  font-size: clamp(1.7rem, 2.5vw + 1.2rem, 2.7rem);
                  line-height: 1.25;
                  color: #0f172a;
                }
                .impact-ngo-header p {
                  margin: 0;
                  color: #475569;
                  font-size: 1.02rem;
                  line-height: 1.65;
                }
                .impact-ngo-metrics {
                  margin-top: clamp(1.5rem, 2.5vw, 2.8rem);
                  display: grid;
                  gap: 1rem;
                  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                }
                .impact-ngo-metric {
                  padding: 1rem 1.25rem;
                  border-radius: 18px;
                  background: rgba(15, 23, 42, 0.05);
                  border: 1px solid rgba(15, 23, 42, 0.08);
                }
                .impact-ngo-metric span {
                  display: block;
                  font-size: 0.78rem;
                  letter-spacing: 0.12em;
                  text-transform: uppercase;
                  color: #475569;
                }
                .impact-ngo-metric strong {
                  display: block;
                  margin-top: 0.35rem;
                  font-size: 1.35rem;
                  color: #0f172a;
                }
                .impact-ngo-grid {
                  margin-top: clamp(2rem, 3vw, 3rem);
                  display: grid;
                  gap: 1rem;
                }
                .impact-ngo-card {
                  display: grid;
                  grid-template-columns: minmax(48px, 68px) 1fr auto;
                  align-items: center;
                  gap: 1.25rem;
                  padding: clamp(0.85rem, 2vw, 1.15rem) clamp(1rem, 3vw, 1.5rem);
                  border-radius: 16px;
                  background: rgba(255, 255, 255, 0.9);
                  border: 1px solid rgba(148, 163, 184, 0.14);
                  box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
                  transition: transform 150ms ease, box-shadow 150ms ease;
                }
                .impact-ngo-card:hover {
                  transform: translateY(-2px);
                  box-shadow: 0 24px 50px rgba(15, 23, 42, 0.12);
                }
                .impact-ngo-rank {
                  width: 100%;
                  aspect-ratio: 1 / 1;
                  border-radius: 14px;
                  display: grid;
                  place-items: center;
                  font-weight: 700;
                  font-size: 1.1rem;
                  color: #0f172a;
                  background: linear-gradient(135deg, rgba(14, 116, 144, 0.16), rgba(14, 165, 233, 0.08));
                }
                .impact-ngo-info h3 {
                  margin: 0;
                  font-size: 1.08rem;
                  color: #0f172a;
                }
                .impact-ngo-amount {
                  display: grid;
                  justify-items: end;
                  gap: 0.25rem;
                }
                .impact-ngo-amount strong {
                  font-size: 1.25rem;
                  font-weight: 700;
                  color: #0284c7;
                }
                .impact-ngo-amount span {
                  font-size: 0.82rem;
                  color: #64748b;
                }
                @media (max-width: 720px) {
                  .impact-ngo-card {
                    grid-template-columns: minmax(44px, 54px) 1fr;
                    grid-template-rows: auto auto;
                    gap: 0.75rem;
                  }
                  .impact-ngo-amount {
                    grid-column: 2 / -1;
                    justify-self: start;
                  }
                }
              </style>
              <div class="impact-ngo-header">
                <span class="impact-ngo-eyebrow">NGO leaderboard</span>
                <h2 id="impact-ngo-title">Támogatott szervezetek teljes toplistája</h2>
                <p>Az Impact Shop vásárlásokból beérkező támogatások összesített rangsora (<?php echo esc_html($from); ?> dátumtól). Az értékek az elfogadott és függőben lévő jóváírásokat egyaránt tartalmazzák.</p>
              </div>
              <div class="impact-ngo-metrics">
                <div class="impact-ngo-metric"><span>Szervezetek száma</span><strong><?php echo esc_html((string)$count); ?></strong></div>
                <div class="impact-ngo-metric"><span>Összes összeg</span><strong><?php echo esc_html($fmt_huf($total_eur)); ?></strong><span><?php echo esc_html('≈ ' . $fmt_eur($total_eur)); ?></span></div>
                <div class="impact-ngo-metric"><span>Legnagyobb támogatás</span><strong><?php echo esc_html($fmt_huf($max_eur)); ?></strong><span><?php echo esc_html('≈ ' . $fmt_eur($max_eur)); ?></span></div>
              </div>
              <div class="impact-ngo-grid">
                <?php if (!$ngo): ?>
                  <div class="impact-ngo-card"><div class="impact-ngo-info"><h3>Nincs adat.</h3></div></div>
                <?php else: ?>
                  <?php foreach ($ngo as $index => $row): ?>
                    <article class="impact-ngo-card">
                      <div class="impact-ngo-rank"><?php echo esc_html((string)($index + 1)); ?></div>
                      <div class="impact-ngo-info">
                        <h3><?php echo esc_html($row['name'] ?? '—'); ?></h3>
                      </div>
                      <div class="impact-ngo-amount">
                        <strong><?php echo esc_html($fmt_huf((float)($row['amount'] ?? 0))); ?></strong>
                        <span><?php echo esc_html('≈ ' . $fmt_eur((float)($row['amount'] ?? 0))); ?></span>
                      </div>
                    </article>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </section>
            <?php
            return ob_get_clean();
        }

        ob_start(); ?>
        <div class="impact-wrap" data-impact-lb>
          <div class="impact-tabs">
            <button class="impact-tab active" data-impact-tab="ngo">Szervezetek</button>
            <button class="impact-tab" data-impact-tab="shop">Webshopok</button>
          </div>
          <div class="impact-card" data-impact-panel="ngo" style="display:block">
            <?php if (!$ngo): ?>
              <div class="impact-empty">Nincs adat.</div>
            <?php else: ?>
              <ol class="impact-list">
                <?php foreach ($ngo as $row): ?>
                  <li><strong><?php echo esc_html($row['name'] ?? '—'); ?></strong> — <?php echo esc_html($fmt($row['amount'] ?? 0)); ?></li>
                <?php endforeach; ?>
              </ol>
            <?php endif; ?>
          </div>
          <div class="impact-card" data-impact-panel="shop" style="display:none">
            <?php if (!$shop): ?>
              <div class="impact-empty">Nincs adat.</div>
            <?php else: ?>
              <ol class="impact-list">
                <?php foreach ($shop as $row): ?>
                  <li><strong><?php echo esc_html($row['name'] ?? '—'); ?></strong> — <?php echo esc_html($fmt($row['amount'] ?? 0)); ?></li>
                <?php endforeach; ?>
              </ol>
            <?php endif; ?>
          </div>
        </div>
        <script>
        (function(){
          const root = document.currentScript.previousElementSibling;
          if (!root) return;
          const tabs = Array.from(root.querySelectorAll('.impact-tab'));
          const panels = Array.from(root.querySelectorAll('[data-impact-panel]'));
          tabs.forEach(tab => {
            tab.addEventListener('click', () => {
              const key = tab.getAttribute('data-impact-tab');
              tabs.forEach(t => t.classList.toggle('active', t === tab));
              panels.forEach(p => {
                p.style.display = p.getAttribute('data-impact-panel') === key ? 'block' : 'none';
              });
            });
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    });
}
