Átugrás a tartalomra
Ezt mondtad:
https://app.sharity.hu/impactshop-new/ kellene az oldal egy social megosztó gomb (lehető legtöbbre. Azt lehet szabályozni, hogy mit osszon meg? Ugyan lesz lead és referral rendszer, de addig is kellene egy sima egyszerű megosztási lehetőség, hogy tudják vinni a hírét az oldalnak. 
A ChatGPT ezt mondta:

Gondolkozás
Jöhet egy „mindenre IS” megosztó, ami mobilon a natív Share panelt hívja, asztali gépen pedig gombokat ad a nagyokhoz (Facebook, WhatsApp, Telegram, X, LinkedIn, Reddit, Pinterest, e-mail, Viber, másolás). A linkbe okosan beletesszük a meglévő d1, amb, src paramétereket, és adunk alap UTM-eket (utm_source=share&utm_medium=social&utm_campaign=impactshop), hogy szépen mérhető legyen — kompatibilisen az ImpactShop átirányítási logikájával, ami szintén továbbviszi az amb/src/utm_* paramétereket. 
Alább egyetlen, bemásolható kódrészlet (Elementor „HTML” widgetbe vagy a tartalomba). A doboz tetején 3 beállítást találsz: data-title, data-text, data-image. Ezekkel szabályozod, mit osszon meg a rendszer. Ha üresen hagyod, a dokumentum címe és a meta leírás lesz használva.

<!-- ImpactShop – Social Share (univerzális, UTM + d1/amb/src megőrzés) -->
<div id="impactshare"
     data-title="Vásárlás = támogatás – Sharity ImpactShop"
     data-text="Nézd meg: itt minden vásárlás 50%-ban adományt generál a választott szervezetnek."
     data-image=""
     style="--btn-gap:10px;--btn-pd:10px;--btn-r:12px;--btn-f:14px">
  <style>
    .ishare{display:flex;flex-wrap:wrap;gap:var(--btn-gap);align-items:center}
    .ishare .btn{display:inline-flex;align-items:center;gap:8px;padding:var(--btn-pd) 12px;border-radius:var(--btn-r);
      border:1px solid #e6e8ea;background:#fff;color:#111;font:600 var(--btn-f)/1.2 system-ui;text-decoration:none}
    .ishare .btn:hover{transform:translateY(-1px)}
    .ishare .copy-ok{font-size:12px;color:#16a34a;margin-left:6px;display:none}
    .ishare .copy.show+.copy-ok{display:inline}
  </style>
  <div class="ishare">
    <a class="btn any" href="#" title="Megosztás (rendszer)">
      <span>Megosztás…</span>
    </a>
    <a class="btn fb" href="#" target="_blank" rel="noopener">Facebook</a>
    <a class="btn wa" href="#" target="_blank" rel="noopener">WhatsApp</a>
    <a class="btn tg" href="#" target="_blank" rel="noopener">Telegram</a>
    <a class="btn tw" href="#" target="_blank" rel="noopener">X</a>
    <a class="btn li" href="#" target="_blank" rel="noopener">LinkedIn</a>
    <a class="btn rd" href="#" target="_blank" rel="noopener">Reddit</a>
    <a class="btn pi" href="#" target="_blank" rel="noopener">Pinterest</a>
    <a class="btn vb" href="#" target="_blank" rel="noopener">Viber</a>
    <a class="btn em" href="#">E-mail</a>
    <a class="btn copy" href="#">Link másolása</a><span class="copy-ok">Kimásolva</span>
  </div>
  <script>
  (function(){
    var root = document.currentScript.parentElement;
    var title = (root.getAttribute('data-title') || document.title).trim();
    var text  = (root.getAttribute('data-text')  || (document.querySelector('meta[name="description"]')?.content || '')).trim();
    var img   = (root.getAttribute('data-image') || (document.querySelector('meta[property="og:image"]')?.content || '')).trim();

    // Param-megőrzés: d1, amb, src + alap UTM-ek
    var url = new URL(location.href);
    var keep = ['d1','amb','src'];
    var u = new URL(location.origin + location.pathname);
    url.searchParams.forEach((v,k)=>{ if(keep.includes(k)) u.searchParams.set(k,v); });
    if(!u.searchParams.has('utm_source')) u.searchParams.set('utm_source','share');
    if(!u.searchParams.has('utm_medium')) u.searchParams.set('utm_medium','social');
    if(!u.searchParams.has('utm_campaign')) u.searchParams.set('utm_campaign','impactshop');

    var shareUrl = u.toString();
    var enc = encodeURIComponent;

    // Natív Share (mobil/modern böngészők)
    var any = root.querySelector('.btn.any');
    any.addEventListener('click', function(e){
      e.preventDefault();
      if (navigator.share) {
        navigator.share({ title: title, text: text, url: shareUrl }).catch(()=>{});
      } else {
        // Ha nincs natív, ugorjon a Facebookra
        window.open('https://www.facebook.com/sharer/sharer.php?u='+enc(shareUrl),'_blank');
      }
    });

    // Platform-linkek
    root.querySelector('.btn.fb').href = 'https://www.facebook.com/sharer/sharer.php?u='+enc(shareUrl);
    root.querySelector('.btn.wa').href = 'https://api.whatsapp.com/send?text='+enc(text? (text+' ') : '')+enc(shareUrl);
    root.querySelector('.btn.tg').href = 'https://t.me/share/url?url='+enc(shareUrl)+'&text='+enc(text||title);
    root.querySelector('.btn.tw').href = 'https://twitter.com/intent/tweet?text='+enc(text||title)+'&url='+enc(shareUrl);
    root.querySelector('.btn.li').href = 'https://www.linkedin.com/sharing/share-offsite/?url='+enc(shareUrl);
    root.querySelector('.btn.rd').href = 'https://www.reddit.com/submit?url='+enc(shareUrl)+'&title='+enc(title);
    root.querySelector('.btn.pi').href = 'https://pinterest.com/pin/create/button/?url='+enc(shareUrl)+(img?('&media='+enc(img)):'')+'&description='+enc(text||title);
    root.querySelector('.btn.vb').href = 'viber://forward?text='+enc((text?text+' ':'')+shareUrl);
    root.querySelector('.btn.em').href = 'mailto:?subject='+enc(title)+'&body='+enc((text?text+'\n\n':'')+shareUrl);

    // Másolás
    var copyBtn = root.querySelector('.btn.copy');
    copyBtn.addEventListener('click', function(e){
      e.preventDefault();
      var done = root.querySelector('.copy-ok');
      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(shareUrl).then(()=>{ copyBtn.classList.add('show'); setTimeout(()=>copyBtn.classList.remove('show'),1300); });
      } else {
        var ta = document.createElement('textarea'); ta.value=shareUrl; document.body.appendChild(ta); ta.select();
        try{ document.execCommand('copy'); copyBtn.classList.add('show'); setTimeout(()=>copyBtn.classList.remove('show'),1300); }catch(e){}
        ta.remove();
      }
    });
  })();
  </script>
</div>
Hogyan szabályozható „mit osszon meg”?
Cím / szöveg / kép: a fenti blokk data-title, data-text, data-image attribútumaival változtathatod oldalanként.
OG címkék (ajánlott): a közösségi oldalak elsősorban a Open Graph meta tagjeidet olvassák. Ha van Yoast/RankMath, állítsd be ott az oldal Social/OG beállításait (cím, leírás, kép). Ha nincs SEO plugin, egy HTML widgetben tehetsz oldal-specifikus OG tageket a <head>-be injektáló pluginnal.
Paraméterek megőrzése: a gombok a jelenlegi URL-ből átveszik a d1, amb, src paramétereket, és UTM-et is kapnak; ez passzol az ImpactShop átirányításhoz, ami szintén továbbadja az amb/src/utm_* mezőket, így a forrás mérhető marad. 
Ha kell, teszek mellé egy kisméretű „lebegő” változatot is a teljes oldalon, vagy ikon-only minimal nézetet — és be tudjuk drótozni GA4 eventekre (pl. share_click platform dimenzióval), hogy lásd, honnan jön a zaj.



A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
