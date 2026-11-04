<?php
/**
 * THH Footer v2 (TEST) — shortcode [thh_footer_v2]
 * Alleen bedoeld voor de privé testpagina /footer-test/; verandert niets aan de huidige footer.
 * Velden met "IN TE VULLEN" moeten nog bevestigd worden door Ismaïl.
 */
if (!defined('ABSPATH')) return;

add_shortcode('thh_footer_v2', function () {
	$u = function ($p) { return esc_url(home_url($p)); };
	$img = esc_url(plugins_url('mollie-payments-for-woocommerce/public/images/'));
	$pay = [
		['creditcard', 'Visa / Mastercard'], ['bancontact', 'Bancontact'], ['klarna', 'Klarna'],
		['applepay', 'Apple Pay'], ['paypal', 'PayPal'], ['kbc', 'KBC/CBC'], ['in3', 'in3'], ['riverty', 'Riverty'],
	];
	ob_start(); ?>
<div class="thf2">
	<section class="thf2-help">
		<div class="thf2-help-txt">
			<h2>Kunnen we je ergens mee helpen?</h2>
			<p>Stuur ons een <strong>WhatsApp</strong> of <strong>mail</strong>, dan helpen we je zo snel mogelijk. In onze winkels helpen we je ook graag persoonlijk.</p>
		</div>
		<ul class="thf2-help-list">
			<li><a href="https://wa.me/31611789629" rel="nofollow noopener" target="_blank"><span class="thf2-ic thf2-ic-wa"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm0 18.15c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 4.54 0 8.24 3.7 8.24 8.24 0 4.55-3.7 8.24-8.24 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.12-.16.25-.64.81-.78.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.13-.15.17-.25.25-.42.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.22-.16-.47-.28z"/></svg></span><strong>WhatsApp</strong><span>06 11 78 96 29</span><span class="thf2-arr">↗</span></a></li>
			<li><a href="mailto:klantenservice@thehealthhouse.nl"><span class="thf2-ic thf2-ic-ml"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span><strong>E-mail</strong><span>klantenservice@thehealthhouse.nl</span><span class="thf2-arr">↗</span></a></li>
		</ul>
	</section>

	<section class="thf2-main">
		<div class="thf2-news">
			<p>Ontvang <strong>15% welkomstkorting</strong> en hoor als eerste over <strong>deals en nieuwe producten</strong>:</p>
			<form class="thf2-form" id="thf2News" novalidate>
				<input type="email" name="email" placeholder="Jouw e-mailadres..." aria-label="E-mailadres" required>
				<button type="submit">Schrijf mij in</button>
				<p class="thf2-err" hidden>Vul een geldig e-mailadres in.</p>
			</form>
			<div class="thf2-ok" id="thf2NewsOk" hidden>
				<p><strong>Gelukt — je staat op de lijst</strong></p>
				<p>Je kortingscode is: <strong>WELKOM15</strong></p>
				<p>Gebruik deze code bij het afrekenen voor 15% korting op je eerste bestelling.</p>
			</div>
			<div class="thf2-social">
				<a href="https://www.instagram.com/healthhouse.nederland/" aria-label="Instagram" rel="noopener" target="_blank">IG</a>
				<a href="https://www.facebook.com/healthhouse.nederland/" aria-label="Facebook" rel="noopener" target="_blank">FB</a>
				<a href="https://www.tiktok.com/@thehealthhouse.nll" aria-label="TikTok" rel="noopener" target="_blank">TT</a>
			</div>
		</div>
		<nav class="thf2-col" aria-label="Klantenservice">
			<h3>Klantenservice</h3>
			<a href="<?php echo $u('/faq/'); ?>">Veelgestelde vragen</a>
			<a href="<?php echo $u('/contact/'); ?>">Contact</a>
			<a href="<?php echo $u('/betaling/'); ?>">Betaalmogelijkheden</a>
			<a href="<?php echo $u('/faq/'); ?>">Verzending</a>
			<a href="<?php echo $u('/retourneren/'); ?>">Retourneren</a>
			<a href="<?php echo $u('/terugroepacties/'); ?>">Terugroepacties</a>
		</nav>
		<nav class="thf2-col" aria-label="Shop">
			<h3>Shop</h3>
			<a href="<?php echo $u('/shop/'); ?>">Alle producten</a>
			<a href="<?php echo $u('/dagdeal/'); ?>">Dagdeal</a>
			<a href="<?php echo $u('/weekdeals/'); ?>">Weekdeals</a>
			<a href="<?php echo $u('/merken/'); ?>">Merken</a>
			<a href="<?php echo $u('/advies/'); ?>">Gratis advies</a>
			<a href="<?php echo $u('/gratis-tools/'); ?>">Gratis tools</a>
			<a href="<?php echo $u('/blog/'); ?>">Blog</a>
		</nav>
		<nav class="thf2-col" aria-label="Over The Health House">
			<h3>Over The Health House</h3>
			<a href="<?php echo $u('/over-the-health-house/'); ?>">Ons verhaal</a>
			<a href="<?php echo $u('/locaties/'); ?>">Onze winkels</a>
			<a href="<?php echo $u('/loyalty/'); ?>">Loyalty club</a>
			<a href="<?php echo $u('/werken-bij/'); ?>">Werken bij</a>
			<a href="<?php echo $u('/franchise/'); ?>">Franchise</a>
		</nav>
		<nav class="thf2-col" aria-label="Zakelijk">
			<h3>Zakelijk</h3>
			<a href="<?php echo $u('/zakelijk-account/'); ?>">Zakelijk account aanvragen</a>
			<a href="<?php echo $u('/contact/'); ?>">Samenwerken / affiliate</a>
		</nav>
	</section>

	<section class="thf2-bottom">
		<div class="thf2-legal">
			<span>© <?php echo esc_html(gmdate('Y')); ?> The Health House Nederland · KvK 97962996</span>
			<span>Overhoeksplein 1, 1031 KS Amsterdam</span>
			<a href="<?php echo $u('/privacy-beleid/'); ?>">Privacybeleid</a>
			<a href="<?php echo $u('/cookies/'); ?>">Cookies</a>
		</div>
		<div class="thf2-pay" aria-label="Betaalmethoden en verzending">
			<span class="thf2-badge thf2-txt">iDEAL</span>
			<?php foreach ($pay as $p) : ?>
				<span class="thf2-badge"><img src="<?php echo $img . $p[0]; ?>.svg" alt="<?php echo esc_attr($p[1]); ?>" loading="lazy" width="38" height="24"></span>
			<?php endforeach; ?>
			<span class="thf2-badge thf2-txt">PostNL</span>
		</div>
	</section>
</div>
<style>
.thf2{--nv:#14204a;--rd:#e2373f;--ln:rgba(255,255,255,.14);background:var(--nv);color:#fff;font-family:inherit;padding:40px 24px 24px;border-radius:0}
.thf2 a{color:#fff;text-decoration:none}.thf2 a:hover{text-decoration:underline}
.thf2-help{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:32px;background:#fff;color:var(--nv);border-radius:18px;padding:32px 40px;max-width:1200px;margin:0 auto 40px}
.thf2-help h2{color:var(--nv);font-size:28px;margin:0 0 12px}.thf2-help p{margin:0;font-size:16px;line-height:1.5}
.thf2-help-list{list-style:none;margin:0;padding:0}.thf2-help-list li{border-bottom:1px solid #e5e7ef}.thf2-help-list li:last-child{border:0}
.thf2-help-list a{display:grid;grid-template-columns:34px 110px minmax(0,1fr) 20px;overflow-wrap:anywhere;align-items:center;gap:8px;padding:16px 0;color:var(--nv)}
.thf2-arr{text-align:right}
.thf2-main{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr 1fr;gap:32px;max-width:1200px;margin:0 auto;padding-bottom:32px;border-bottom:1px solid var(--ln)}
.thf2-news,.thf2-news p,.thf2-news strong{color:#fff!important}.thf2-news p{margin:0 0 14px;line-height:1.5}.thf2-col h3{font-size:15px!important;letter-spacing:.02em}.thf2-form{display:flex;flex-direction:column;gap:10px}
.thf2-form input{border:0;border-radius:999px;padding:16px 22px;font-size:15px;color:#111}
.thf2-form button{border:0;border-radius:999px;padding:16px;background:var(--rd);color:#fff;font-weight:700;cursor:pointer}
.thf2-err{color:#ffb3b8!important;font-size:13px;margin:0!important}.thf2-ok{background:rgba(255,255,255,.08);border-radius:14px;padding:16px}.thf2-ok p{margin:0 0 6px!important}
.thf2-social{display:flex;gap:10px;margin-top:18px}.thf2-social a{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.1);display:grid;place-items:center;font-weight:700;font-size:13px}
.thf2-col{display:flex;flex-direction:column;gap:10px}.thf2-col h3{color:#fff;font-size:16px;margin:0 0 6px}.thf2-col a{opacity:.85;font-size:15px}
.thf2-bottom{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;max-width:1200px;margin:0 auto;padding-top:20px}
.thf2-legal{display:flex;gap:16px;flex-wrap:wrap;font-size:13px;opacity:.8}
.thf2-pay{display:flex;gap:6px;flex-wrap:wrap}.thf2-badge{background:#fff;border-radius:6px;height:32px;min-width:50px;display:grid;place-items:center;padding:0 6px}
.thf2-badge img{height:22px;width:auto}.thf2-txt{color:var(--nv);font-weight:800;font-size:12px}
@media(max-width:900px){.thf2-help{grid-template-columns:minmax(0,1fr);padding:24px}.thf2-main{grid-template-columns:1fr 1fr}.thf2-news{grid-column:1/-1}}
@media(max-width:520px){.thf2{padding:24px 16px}.thf2-help{padding:20px 18px}.thf2-help h2{font-size:22px;overflow-wrap:anywhere}.thf2-help-list a{grid-template-columns:34px minmax(0,1fr) 16px;row-gap:2px}.thf2-help-list a>*:nth-child(3){grid-column:2}.thf2-help-list a>*:nth-child(4){grid-column:3;grid-row:1}.thf2 .thf2-help-list{padding:0!important;margin:0!important}.thf2-help-list a{column-gap:12px}.thf2-help-list a>span:nth-child(3){overflow-wrap:normal;word-break:normal;white-space:nowrap;font-size:min(15px,3.7vw);letter-spacing:-.01em}.thf2-main{grid-template-columns:1fr}}
.thf2-ic{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;color:#fff}.thf2-ic svg{width:18px;height:18px;display:block}.thf2-ic-wa{background:#25D366}.thf2-ic-wa svg{width:20px;height:20px}.thf2-ic-ml{background:var(--nv)}
</style>
<script>(function(){var f=document.getElementById('thf2News');if(!f||f.dataset.b)return;f.dataset.b=1;f.addEventListener('submit',function(e){e.preventDefault();var m=f.querySelector('input[name=email]'),er=f.querySelector('.thf2-err'),v=(m.value||'').trim();if(!/^[^@\s]+@[^@\s.]+\.[^@\s]{2,}$/.test(v)){er.hidden=false;m.focus();return}er.hidden=true;var b=f.querySelector('button');b.disabled=true;var d=new FormData();d.append('action','thh_popup_signup');d.append('email',v);d.append('bron','footer');fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,{method:'POST',body:d,credentials:'same-origin'}).then(function(r){return r.json()}).catch(function(){return{}}).then(function(){f.hidden=true;document.getElementById('thf2NewsOk').hidden=false})})})();</script>
<?php
	return ob_get_clean();
});

// Live: oude Elementor-footer verbergen (blijft bewaard als back-up) en de nieuwe tonen.
// Terugdraaien: optie thh_footer_v2_live op 0 zetten (of deze snippet uitzetten).
add_action('wp_head', function () {
	if (!get_option('thh_footer_v2_live') || is_admin() || is_page('footer-test')) return;
	echo '<style id="thf2-live">.elementor-location-footer{display:none!important}</style>';
}, 99);
add_action('wp_footer', function () {
	if (!get_option('thh_footer_v2_live') || is_admin() || is_page('footer-test')) return;
	echo '<footer class="thf2-site" role="contentinfo">' . do_shortcode('[thh_footer_v2]') . '</footer>';
}, 1);
add_action('rest_api_init', function () {
	register_rest_route('thh/v1', '/footer-v2', ['methods' => 'POST', 'permission_callback' => function () { return current_user_can('manage_options'); }, 'callback' => function ($r) {
		update_option('thh_footer_v2_live', $r['live'] ? 1 : 0, true);
		global $kinsta_cache;
		if (isset($kinsta_cache->kinsta_cache_purge) && method_exists($kinsta_cache->kinsta_cache_purge, 'purge_complete_caches')) $kinsta_cache->kinsta_cache_purge->purge_complete_caches();
		return ['live' => (bool) get_option('thh_footer_v2_live')];
	}]);
});
