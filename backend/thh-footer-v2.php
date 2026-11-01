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
			<li><a href="https://wa.me/31611789629" rel="nofollow noopener" target="_blank"><span class="thf2-ic">💬</span><strong>WhatsApp</strong><span>06 11 78 96 29</span><span class="thf2-arr">↗</span></a></li>
			<li><a href="mailto:klantenservice@thehealthhouse.nl"><span class="thf2-ic">✉️</span><strong>E-mail</strong><span>klantenservice@thehealthhouse.nl</span><span class="thf2-arr">↗</span></a></li>
		</ul>
	</section>

	<section class="thf2-main">
		<div class="thf2-news">
			<p>Ontvang <strong>15% welkomstkorting</strong> en hoor als eerste over <strong>deals en nieuwe producten</strong>:</p>
			<form class="thf2-form" onsubmit="return false">
				<input type="email" placeholder="Jouw e-mailadres..." aria-label="E-mailadres">
				<button type="submit">Schrijf mij in</button>
			</form>
			<p class="thf2-note">(Op de echte footer koppelen we hier het bestaande Klaviyo-formulier.)</p>
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
			<a href="<?php echo $u('/faq/'); ?>">Verzending (pagina nog maken)</a>
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
			<a href="https://franchise.thehealthhouse.nl/">Franchise</a>
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
			<a href="<?php echo $u('/faq/'); ?>">Algemene voorwaarden (pagina nog maken)</a>
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
.thf2-help{display:grid;grid-template-columns:1fr 1fr;gap:32px;background:#fff;color:var(--nv);border-radius:18px;padding:32px 40px;max-width:1200px;margin:0 auto 40px}
.thf2-help h2{color:var(--nv);font-size:28px;margin:0 0 12px}.thf2-help p{margin:0;font-size:16px;line-height:1.5}
.thf2-help-list{list-style:none;margin:0;padding:0}.thf2-help-list li{border-bottom:1px solid #e5e7ef}.thf2-help-list li:last-child{border:0}
.thf2-help-list a{display:grid;grid-template-columns:28px 110px 1fr 20px;align-items:center;gap:8px;padding:16px 0;color:var(--nv)}
.thf2-arr{text-align:right}
.thf2-main{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr 1fr;gap:32px;max-width:1200px;margin:0 auto;padding-bottom:32px;border-bottom:1px solid var(--ln)}
.thf2-news,.thf2-news p,.thf2-news strong{color:#fff!important}.thf2-news p{margin:0 0 14px;line-height:1.5}.thf2-col h3{font-size:15px!important;letter-spacing:.02em}.thf2-form{display:flex;flex-direction:column;gap:10px}
.thf2-form input{border:0;border-radius:999px;padding:16px 22px;font-size:15px;color:#111}
.thf2-form button{border:0;border-radius:999px;padding:16px;background:var(--rd);color:#fff;font-weight:700;cursor:pointer}
.thf2-note{font-size:12px;opacity:.6;margin-top:8px!important}
.thf2-social{display:flex;gap:10px;margin-top:18px}.thf2-social a{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.1);display:grid;place-items:center;font-weight:700;font-size:13px}
.thf2-col{display:flex;flex-direction:column;gap:10px}.thf2-col h3{color:#fff;font-size:16px;margin:0 0 6px}.thf2-col a{opacity:.85;font-size:15px}
.thf2-bottom{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;max-width:1200px;margin:0 auto;padding-top:20px}
.thf2-legal{display:flex;gap:16px;flex-wrap:wrap;font-size:13px;opacity:.8}
.thf2-pay{display:flex;gap:6px;flex-wrap:wrap}.thf2-badge{background:#fff;border-radius:6px;height:32px;min-width:50px;display:grid;place-items:center;padding:0 6px}
.thf2-badge img{height:22px;width:auto}.thf2-txt{color:var(--nv);font-weight:800;font-size:12px}
@media(max-width:900px){.thf2-help{grid-template-columns:1fr;padding:24px}.thf2-main{grid-template-columns:1fr 1fr}.thf2-news{grid-column:1/-1}}
@media(max-width:520px){.thf2{padding:24px 16px}.thf2-help-list a{grid-template-columns:24px 80px 1fr 16px}.thf2-main{grid-template-columns:1fr}}
</style>
<?php
	return ob_get_clean();
});
