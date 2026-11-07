<?php
/**
 * THH — Loyalty en Voorraad in het kassamenu
 *
 * Zet twee knoppen in de linkerbalk van de kassa (/pos/), onder de eigen knoppen van de kassa:
 *  - Loyalty  → opent het loyalty-paneel van snippet "THH POS Loyalty" (#thh-pl-toggle); de losse knop linksonder gaat weg
 *  - Voorraad → opent het voorraadregister zoals de knop "Voorraad tellen" (#thh-vrrpos-btn); die knop blijft staan
 * De knoppen klikken de bestaande knoppen aan, dus de werking zelf verandert niet.
 */
if (!defined('ABSPATH')) return;

add_action('woocommerce_pos_footer', function () {
	?>
<style>
#thh-pl-toggle{display:none!important}
.thh-km-item svg{width:24px;height:24px;fill:currentColor}
.thh-km-item{color:rgba(255,255,255,.85)}
.thh-km-item:hover{color:#fff}
</style>
<script>
(function(){
var ITEMS=[
 {id:'loyalty',title:'Loyalty',doel:'#thh-pl-toggle',svg:'<svg viewBox="0 0 576 512"><path d="M316.9 18c-5.3-11-16.5-18-28.8-18s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 329l-24.6 145.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L448.6 329l104.2-103.1c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L391.2 150.3 316.9 18z"/></svg>'},
 {id:'voorraad',title:'Voorraad tellen',doel:'#thh-vrrpos-btn',svg:'<svg viewBox="0 0 512 512"><path d="M248.3 1.7c4.8-2.3 10.5-2.3 15.3 0l224 106.7c9.4 4.5 15.3 13.8 15.3 24.2V480c0 17.7-14.3 32-32 32H41.2c-17.7 0-32-14.3-32-32V132.6c0-10.4 5.9-19.7 15.3-24.2l224-106.7zM80 192v96h128v-96H80zm224 0v96h128v-96H304zM80 352v96h128v-96H80zm224 0v96h128v-96H304z"/></svg>'}
];
function zet(){
  var ref=document.querySelector('button[data-testid^="drawer-item-"]');
  if(!ref)return;
  var kol=ref.closest('.flex-col');if(!kol)return;
  var bak=kol.parentElement;if(!bak)return;
  ITEMS.forEach(function(it){
    if(bak.querySelector('[data-thh-km="'+it.id+'"]'))return;
    var d=document.createElement('div');d.className=kol.className;
    var w=document.createElement('div');w.className=ref.parentElement.className;
    var b=document.createElement('button');b.type='button';b.className=ref.className+' thh-km-item';b.title=it.title;b.setAttribute('aria-label',it.title);b.setAttribute('data-thh-km',it.id);
    b.innerHTML='<div class="'+ref.firstElementChild.className+'">'+it.svg+'</div>';
    b.addEventListener('click',function(){var t=document.querySelector(it.doel);if(t){t.click()}else{alert(it.title+' is niet beschikbaar in deze winkel.')}});
    w.appendChild(b);d.appendChild(w);bak.appendChild(d);
  });
}
setInterval(zet,1500);zet();
})();
</script>
	<?php
});
