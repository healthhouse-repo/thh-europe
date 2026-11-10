<?php
/**
 * THH Taal automatisch (thehealthhouse.nl)
 *
 * Eerste bezoek zonder eigen taalkeuze: kies de taal van de browser.
 * Nederlands -> NL, Duits -> /de/, Engels of een andere taal -> /en/.
 * De keuze wordt 1 jaar onthouden (cookie thh_taal); kiest de bezoeker zelf
 * een taal via de taalknop, dan geldt die. Zoekmachines worden niet omgeleid.
 * Werkt alleen als de vertaallaag (snippet 446) live staat.
 */
add_action('wp_head', function () {
	if (is_admin() || !function_exists('thh_i18n_live') || !thh_i18n_live()) return;
	$js = <<<'JS'
<script id="thh-taal-auto">(function(){
var C="thh_taal";
function set(l){try{document.cookie=C+"="+l+";path=/;max-age=31536000;SameSite=Lax";}catch(e){}}
function langOf(path){var m=path.match(/^\/(de|en)(\/|$)/);return m?m[1]:"nl";}
document.addEventListener("click",function(e){var a=e.target&&e.target.closest&&e.target.closest(".thh-lang a[href]");if(!a)return;try{set(langOf(new URL(a.href,location.href).pathname));}catch(x){}},true);
if(/bot|crawl|spider|slurp|facebookexternalhit|lighthouse|headless|preview/i.test(navigator.userAgent||""))return;
if(document.cookie.indexOf(C+"=")>-1)return;
var p=location.pathname;
if(/^\/(wp-|pos|wcpos|kassa)|afrekenen|checkout|order-received|mijn-account|my-account|winkelwagen|cart/i.test(p))return;
var L=[].concat(navigator.languages||[],navigator.language||[]),want="en";
for(var i=0;i<L.length;i++){var c=String(L[i]||"").toLowerCase().slice(0,2);if(c==="nl"||c==="de"||c==="en"){want=c;break;}}
set(want);
var cur=langOf(p);if(want===cur)return;
var rest=cur==="nl"?p:(p.slice(3)||"/");
location.replace((want==="nl"?"":"/"+want)+rest+location.search+location.hash);
})();</script>
JS;
	echo $js;
}, 1);
