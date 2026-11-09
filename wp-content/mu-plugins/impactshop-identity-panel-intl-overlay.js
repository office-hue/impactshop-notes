(function(){
  'use strict';

  var CATEGORY_TRANSLATIONS = {
    'Összes': 'All',
    'Ajándék': 'Gift',
    'Bútor': 'Furniture',
    'Divat': 'Fashion',
    'Egészség': 'Health',
    'Elektronika': 'Electronics',
    'Játék': 'Games',
    'Kultúra': 'Culture',
    'Műszaki': 'Tech',
    'Otthon': 'Home',
    'Sport': 'Sports',
    'Szabadidő': 'Leisure',
    'Vegyes': 'Mixed',
    'Állat': 'Pets',
    'Ékszer': 'Jewelry',
    'Élelmiszer': 'Groceries'
  };

  var EXACT_TRANSLATIONS = {
    'Videó': 'Video',
    'Feladatok': 'Tasks',
    'Adományozok': 'Donate',
    'Impact hírek': 'Impact news',
    'Üzenetek': 'Messages',
    'Pontok': 'Points',
    'Kupon': 'Coupon',
    'Kuponok %': 'Coupons %',
    'KUPONOK %': 'COUPONS %',
    'AKCIÓ': 'DEAL',
    '🌍 Nyelv és ország': '🌍 Language and country',
    'Nyelv': 'Language',
    'Ország': 'Country',
    'Gyors műveletek': 'Quick actions',
    'Nyelv és ország választó': 'Language and country selector',
    'KEDVEZMÉNYES AJÁNLATOK': 'FEATURED OFFERS',
    'MEGMUTATOD?': 'WILL YOU SHARE IT?',
    'Hogyan működik?': 'How does it work?',
    'Hogyan működik ?': 'How does it work?',
    'Válassz boltot': 'Choose a store',
    'Válassz ügyet': 'Choose a cause',
    'Vásárolj és támogass': 'Shop and support',
    'Top ajánlatok': 'Top offers',
    'Adomány mértéke (%)': 'Donation rate (%)',
    'Mennyi adományt gyűjtök ?': 'How much donation am I collecting?',
    'aktív': 'active'
  };

  var LOCALE_PATH_PATTERNS = [
    /^\/impactshop(\/|$)/,
    /^\/impact-challenge(\/|$)/,
    /^\/impactad-2(\/|$)/,
    /^\/ngo-guides(\/|$)/,
    /^\/rolunk\/?$/,
    /^\/cegeknek\/?$/,
    /^\/befektetoknek\/?$/,
    /^\/hatas-korok\/?$/,
    /^\/profil\/?$/
  ];

  var USD_RATE_HUF_DISPLAY_FAILSAFE = 311.28;

  var EXACT_RENDERED_TRANSLATIONS = {
    'Ha meg akarod tartani, kattints a Save gombra, hogy a jelszavaid közé kerüljön.': 'If you want to keep it, click Save so it is added to your saved passwords.',
    'Ha máshova mentenéd, használd a Copy gombot.': 'If you want to save it elsewhere, use Copy.',
    'Ha később visszajössz és a fiókod nem jelenik meg, kattints a Restore gombra.': 'If you come back later and your account does not appear, click Restore.',
    'Egy kattintással megoszthatod a kedvenc platformjaidon, így segíthetsz eljuttatni egy jó ügyet, minél több emberhez.': 'With one click you can share it on your favorite platforms and help a good cause reach more people.',
    'Egy kattintással megoszthatod a kedvenc platformjaidon, így segíthetsz eljuttatni egy good causeet, minél több emberhez.': 'With one click you can share it on your favorite platforms and help a good cause reach more people.'
  };

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
      return;
    }
    callback();
  }

  function singularOrPlural(numberText, singular, plural) {
    var normalized = String(numberText || '').replace(/[^0-9]/g, '').trim();
    return normalized === '1' ? singular : plural;
  }

  function currentUsdRateHuf() {
    var rate = Number(window.impactshopAdsWatch && window.impactshopAdsWatch.usdRateHuf);
    if (Number.isFinite(rate) && rate > 0) {
      return rate;
    }
    return USD_RATE_HUF_DISPLAY_FAILSAFE;
  }

  function parseHungarianAmount(value) {
    var normalized = String(value || '')
      .replace(/[^0-9,.\x00-\x7f]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    normalized = normalized.replace(/\./g, '').replace(/,/g, '.').replace(/\s+/g, '');
    var parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function formatUsdAmountFromHuf(amountText) {
    var amountHuf = parseHungarianAmount(amountText);
    if (!Number.isFinite(amountHuf) || amountHuf <= 0) return null;
    var usd = amountHuf / currentUsdRateHuf();
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: usd < 100 ? 2 : 0,
      maximumFractionDigits: usd < 100 ? 2 : 0
    }).format(usd);
  }

  function translateUsdShopCurrencyText(text) {
    if (!isShopPage() || currentLocale().country !== 'us') return text;

    var translated = String(text || '');
    var replacements = [
      [/Egy kattintással megoszthatod a kedvenc platformjaidon, így segíthetsz eljuttatni egy(?: jó ügyet| good causeet), minél több emberhez\./g, 'With one click you can share it on your favorite platforms and help a good cause reach more people.'],
      [/(\d+)% discount feliratkozós kupon, minden teljes árú termékre,\s*([\d. ]+)\s*(?:HUF|Forint) feletti vásárlás esetén\./g, function(_, pct, amount) {
        var usd = formatUsdAmountFromHuf(amount);
        return pct + '% sign-up discount coupon on all full-price products for purchases above ' + (usd || amount) + '.';
      }],
      [/(\d+)% discount a off sunglasses/g, '$1% discount on sunglasses'],
      [/(\$?[\d.,]+) felett\. Nem válthatóak be törzsvásárlói regisztrációval, mert különböző discountek nem vonhatóak egybe, nem halmozhatóak, továbbá csak HUF checkout történő fizetés esetén beválthatóak az oldalon\./g, function(_, amount) {
        return 'Valid for purchases above ' + amount + '. Loyalty registration discounts cannot be combined or stacked, and redemption on the site remains tied to HUF checkout pricing.';
      }],
      [/AKCIÓ/g, 'DEAL'],
      [/Kid Cars járművek többféle/g, 'Kid Cars vehicles assorted'],
      [/napszemüvegekre/g, 'off sunglasses'],
      [/kedvezmény a kiegészítőkre/g, 'discount on accessories'],
      [/kedvezmény/g, 'discount'],
      [/Ingyen jutalomfalat minden vásárláshoz\s*([\d. ]+)\s*Ft felett\. Egy felhasználó csak egyszer használhatja/g, function(_, amount) {
        var usd = formatUsdAmountFromHuf(amount);
        return usd
          ? 'Free treat with every purchase above ' + usd + '. One user may redeem it only once.'
          : 'Free treat with every eligible purchase. One user may redeem it only once.';
      }],
      [/([\d. ]+)\s*Ft felett\. Nem válthatóak be törzsvásárlói regisztrációval, mert különböző kedvezmények nem vonhatóak egybe, nem halmozhatóak, továbbá csak Ft-ban történő fizetés esetén beválthatóak az oldalon\./g, function(_, amount) {
        var usd = formatUsdAmountFromHuf(amount);
        return usd
          ? 'Valid for purchases above ' + usd + '. Loyalty registration discounts cannot be combined or stacked, and redemption on the site remains tied to HUF checkout pricing.'
          : 'Valid above the listed purchase threshold. Loyalty registration discounts cannot be combined or stacked.';
      }],
      [/(\d+)% kedvezmény feliratkozós kupon, minden teljes árú termékre,\s*([\d. ]+)\s*Forint feletti vásárlás esetén\./g, function(_, pct, amount) {
        var usd = formatUsdAmountFromHuf(amount);
        return pct + '% sign-up discount coupon on all full-price products for purchases above ' + (usd || amount) + '.';
      }],
      [/(\d+)% kedvezmény a napszemüvegekre/g, '$1% discount on sunglasses'],
      [/(\d+)% kedvezmény a kiegészítőkre/g, '$1% discount on accessories'],
      [/(\d+)% napszemüvegekre/g, '$1% off sunglasses'],
      [/Ft-ban/g, 'HUF checkout'],
      [/Forint/g, 'HUF'],
      [/jó ügy/g, 'good cause'],
      [/szervezet/g, 'organization']
    ];

    for (var i = 0; i < replacements.length; i += 1) {
      translated = translated.replace(replacements[i][0], replacements[i][1]);
    }

    translated = translated.replace(/(\d[\d .]*)\s*Ft\b/g, function(match, amount) {
      return formatUsdAmountFromHuf(amount) || match;
    });

    return translated;
  }

  function translateDynamicText(text) {
    if (typeof text !== 'string' || text === '') return text;

    var translated = text;
    var monthMap = {
      'jan.': 'Jan',
      'febr.': 'Feb',
      'márc.': 'Mar',
      'ápr.': 'Apr',
      'máj.': 'May',
      'jún.': 'Jun',
      'júl.': 'Jul',
      'aug.': 'Aug',
      'szept.': 'Sep',
      'okt.': 'Oct',
      'nov.': 'Nov',
      'dec.': 'Dec'
    };
    var replacements = [
      [/Fiókom információ/g, 'My account information'],
      [/Fiókom/g, 'My account'],
      [/Aktuális üzenet/g, 'Current message'],
      [/Jelenleg nincs új üzenet\./g, 'There is no new message right now.'],
      [/Üzenetek/g, 'Messages'],
      [/Üzenet/g, 'Message'],
      [/Pontok/g, 'Points'],
      [/Fontos:/g, 'Important:'],
      [/a fiókodat automatikusan létrehoztuk\./g, 'your account was created automatically.'],
      [/Ha meg akarod tartani, kattints a Save gombra, hogy a jelszavaid közé kerüljön\./g, 'If you want to keep it, click Save so it is added to your saved passwords.'],
      [/Ha meg akarod tartani, kattints a Mentés gombra, hogy a jelszavaid közé kerüljön\./g, 'If you want to keep it, click Save so it is added to your saved passwords.'],
      [/Ha máshova mentenéd, használd a Copy gombot\./g, 'If you want to save it elsewhere, use Copy.'],
      [/Ha máshova mentenéd, használd a Másolás gombot\./g, 'If you want to save it elsewhere, use Copy.'],
      [/Ha később visszajössz és a fiókod nem jelenik meg, kattints a Restore gombra\./g, 'If you come back later and your account does not appear, click Restore.'],
      [/Ha később visszajössz és a fiókod nem jelenik meg, kattints a Helyreállítás gombra\./g, 'If you come back later and your account does not appear, click Restore.'],
      [/Csak a fiókodban tudod összegyűjteni az előnyöket és a jelvényeket, ezért érdemes elmentened\./g, 'Only your own account can collect your perks and badges, so it is worth saving it.'],
      [/A fiókom kezelése/g, 'Manage my account'],
      [/Ez nem az én fiókom/g, 'This is not my account'],
      [/Legacy Wall/g, 'Legacy Wall'],
      [/Több szervezet/g, 'Multiple NGOs'],
      [/Évforduló/g, 'Anniversary'],
      [/Korai belépő/g, 'Early supporter'],
      [/Korai használó\./g, 'Early user.'],
      [/50 edukáció/g, '50 education videos'],
      [/50 edukációs videó\./g, '50 educational videos completed.'],
      [/Prémium offer/g, 'Premium offer'],
      [/Egy kiemelkedő értékű teljesítés\./g, 'An outstanding high-value completion.'],
      [/10 szervezet/g, '10 NGOs'],
      [/10 különböző szervezet támogatása\./g, 'Support 10 different NGOs.'],
      [/Hűséges támogató/g, 'Loyal supporter'],
      [/Ugyanazon szervezet támogatása 30 napon át\./g, 'Support the same NGO for 30 days.'],
      [/5 offer/g, '5 offers'],
      [/5 offerwall teljesítés\./g, '5 offerwall completions.'],
      [/Quiz mester/g, 'Quiz master'],
      [/10 kvíz sikeres teljesítése\./g, 'Complete 10 quizzes successfully.'],
      [/Első sikeres ajánlás\./g, 'First successful referral.'],
      [/Téli kihívás/g, 'Winter challenge'],
      [/Különleges téli kampány jelvény\./g, 'Special winter campaign badge.'],
      [/30 napos streak/g, '30-day streak'],
      [/Egymás után 30 days aktivitás\./g, '30 days of activity in a row.'],
      [/Egymás után 30 nap aktivitás\./g, '30 days of activity in a row.'],
      [/5000 videó/g, '5000 videos'],
      [/5000 megtekintett videó\./g, '5000 watched videos.'],
      [/5000 votes/g, '5000 votes'],
      [/5000 szavazat/g, '5000 votes'],
      [/5000 leadott szavazat\./g, '5000 votes cast.'],
      [/Első offer/g, 'First offer'],
      [/Első offerwall teljesítés\./g, 'First offerwall completion.'],
      [/Első edukáció/g, 'First education video'],
      [/Első edukációs videó\./g, 'First educational video.'],
      [/Első szervezet/g, 'First NGO'],
      [/Első támogatott szervezet\./g, 'First supported NGO.'],
      [/Első szavazat/g, 'First vote'],
      [/Első szavazat leadása\./g, 'Cast your first vote.'],
      [/1 éve Sharity tag\./g, '1 year on Sharity.'],
      [/2 éve Sharity tag\./g, '2 years on Sharity.'],
      [/3 éve Sharity tag\./g, '3 years on Sharity.'],
      [/4 éve Sharity tag\./g, '4 years on Sharity.'],
      [/5 éve Sharity tag\./g, '5 years on Sharity.'],
      [/Profilod/g, 'Your profile'],
      [/Szia ([^!]+)! Üdvözöllek a Sharity oldalán\./g, 'Hi $1! Welcome to Sharity.'],
      [/Szia, üdvözöllek a Sharity oldalán\./g, 'Hi, welcome to Sharity.'],
      [/Értesítések tiltva/g, 'Notifications disabled'],
      [/Értesítések kikapcsolása/g, 'Turn off notifications'],
      [/Értesítések bekapcsolása/g, 'Turn on notifications'],
      [/Értesítések/g, 'Notifications'],
      [/A profil üzeneteket pushban is elküldjük \(ha engedélyezed\)\./g, 'Profile messages are also sent as push notifications if you allow them.'],
      [/A böngészőben le vannak tiltva az értesítések\./g, 'Notifications are disabled in this browser.'],
      [/Az értesítések engedélyezése szükséges\./g, 'Notification permission is required.'],
      [/Értesítések bekapcsolva\. Safari\/iOS esetén a telepített Sharity appban jelennek meg\./g, 'Notifications enabled. On Safari/iOS they appear in the installed Sharity app.'],
      [/Értesítések bekapcsolva\./g, 'Notifications enabled.'],
      [/Értesítések kikapcsolva\./g, 'Notifications turned off.'],
      [/Push értesítések aktívak\./g, 'Push notifications are active.'],
      [/Azonosító helyreállítás/g, 'ID recovery'],
      [/Azonosítód/g, 'Your ID'],
      [/Azonosító betöltve\./g, 'Identity loaded.'],
      [/Azonosító másolva\./g, 'ID copied.'],
      [/Helyreállító kód másolva\./g, 'Recovery code copied.'],
      [/Azonosító helyreállítva\. Visszaléptetünk…/g, 'Identity restored. Redirecting...'],
      [/Azonosító helyreállítva\./g, 'Identity restored.'],
      [/Adj meg érvényes azonosítót\./g, 'Enter a valid ID.'],
      [/Érvénytelen helyreállító kód formátum\./g, 'Invalid recovery code format.'],
      [/Helyreállítás folyamatban…/g, 'Restore in progress...'],
      [/Helyreállítás sikertelen\./g, 'Restore failed.'],
      [/Helyreállítás hiba\./g, 'Restore error.'],
      [/Helyreállítás…/g, 'Restoring...'],
      [/Helyreállítás/g, 'Restore'],
      [/Helyreállító kód/g, 'Recovery code'],
      [/Azonosító(?!d)/g, 'ID'],
      [/Ezzel kapcsoljuk össze az adományt és a jutalmakat\./g, 'We use this to connect donations and rewards.'],
      [/Őrizd meg az azonosítót és a helyreállító kódot\./g, 'Keep your ID and recovery code safe.'],
      [/Mentés jelszókezelőbe \(opcionális\)/g, 'Save to password manager (optional)'],
      [/A böngésző felajánlhatja a mentést\./g, 'Your browser may offer to save it.'],
      [/Becenév \(opcionális\)/g, 'Nickname (optional)'],
      [/Becenév mentése…/g, 'Saving nickname...'],
      [/Becenév mentése sikertelen\./g, 'Saving nickname failed.'],
      [/Becenév mentve\./g, 'Nickname saved.'],
      [/Becenév mentése hiba\./g, 'Nickname save error.'],
      [/Sikertelen mentés\./g, 'Save failed.'],
      [/Hiba történt\./g, 'An error occurred.'],
      [/Szinted és pontjaid/g, 'Your level and points'],
      [/Miért éri meg\?/g, 'Why does it matter?'],
      [/A magasabb szint nagyobb adománybónuszt és előnyöket ad\. A pontküszöbök tájékoztató jellegűek, a szinteloszlás percentilis alapján is alakul\./g, 'Higher levels provide larger donation bonuses and extra perks. Point thresholds are indicative; level distribution may also vary by percentile.'],
      [/Legmagasabb szint elérve\./g, 'Highest level reached.'],
      [/Adomány bónusz:/g, 'Donation bonus:'],
      [/Szavazati súly:/g, 'Vote weight:'],
      [/Max kedvezményből:/g, 'Max discount:'],
      [/Legutóbbi aktivitás/g, 'Recent activity'],
      [/Még nincs aktivitás\./g, 'No activity yet.'],
      [/Impact Shop vásárlás/g, 'Impact Shop purchase'],
      [/Első vásárlás/g, 'First purchase'],
      [/Shop felfedezés/g, 'Shop discovery'],
      [/Ads videó/g, 'Ad video'],
      [/Szponzori videó/g, 'Sponsored video'],
      [/Megosztás/g, 'Share'],
      [/Visszajelzés/g, 'Feedback'],
      [/Ajánlói bónusz/g, 'Referral bonus'],
      [/Ajánlás/g, 'Referral'],
      [/Profil kitöltése/g, 'Profile completion'],
      [/Becenév mentés/g, 'Nickname save'],
      [/Napi belépés/g, 'Daily login'],
      [/Sorozat bónusz/g, 'Streak bonus'],
      [/Wallet letöltés/g, 'Wallet download'],
      [/Digitális tombola/g, 'Digital raffle'],
      [/Bónusz/g, 'Bonus'],
      [/Pontkorrekció/g, 'Points adjustment'],
      [/Vakáció mód/g, 'Vacation mode'],
      [/Inaktivitás/g, 'Inactivity'],
      [/Pontmozgás/g, 'Points activity'],
      [/Jelenleg nincs eltárolt NGO választásod\./g, 'You do not have a stored NGO choice yet.'],
      [/Legutóbb ezt a szervezetet támogattad:/g, 'You last supported this NGO:'],
      [/Másikat választok/g, 'Choose another'],
      [/Nem sikerült betölteni a legutóbbi NGO-t\./g, 'Could not load the last NGO.'],
      [/Vakáció aktív:\s*(\d+)\s+nap hátra\./g, 'Vacation active: $1 days left.'],
      [/Nincs aktív vakáció\. Éves alkalmak:\s*(\d+)/g, 'No active vacation. Yearly activations: $1'],
      [/Bekapcsolás/g, 'Enable'],
      [/Kikapcsolás/g, 'Disable'],
      [/Infó/g, 'Info'],
      [/Oszd meg a kódot, hogy jutalmat kapjatok\./g, 'Share the code so both of you can earn rewards.'],
      [/Még nincs jelvényed\./g, 'You do not have any badges yet.'],
      [/Legacy Pool helyezés:/g, 'Legacy Pool position:'],
      [/Pontszám:/g, 'Score:'],
      [/Jelvény/g, 'Badge'],
      [/Új eszközön add meg az azonosítót és a helyreállító kódot\./g, 'On a new device, enter your ID and recovery code.'],
      [/Nem sikerült azonosítót kérni\. Próbáld újra\./g, 'Could not request an ID. Please try again.'],
      [/Támogatásaim összege:\s*([\d\s.,]+)\s*Ft/g, 'Total donations: $1 HUF'],
      [/A push nem elérhető \(SW\)\./g, 'Push is not available (SW).'],
      [/Frissítés folyamatban…/g, 'Refreshing...'],
      [/Nincs aktív azonosító vagy kód\./g, 'No active ID or code.'],
      [/Nincs aktív azonosító\./g, 'No active ID.'],
      [/Nincs aktív kód\./g, 'No active code.'],
      [/Másolás sikertelen\./g, 'Copy failed.'],
      [/Megosztás indítása…/g, 'Starting share...'],
      [/Megosztás megszakítva\./g, 'Share cancelled.'],
      [/Megosztás helyett másolva\./g, 'Copied instead of sharing.'],
      [/Másold ki az adatokat a felugró ablakból\./g, 'Copy the details from the dialog.'],
      [/Másold ki az adatokat:/g, 'Copy the details:'],
      [/Mentve a jelszókezelőbe\./g, 'Saved to password manager.'],
      [/A mentés ezen a nézeten nem érhető el\./g, 'Saving is not available in this view.'],
      [/Jelszókezelő mentés indítva\./g, 'Password manager save started.'],
      [/Vakáció kikapcsolása sikertelen\./g, 'Disabling vacation failed.'],
      [/Vakáció indítása sikertelen\./g, 'Starting vacation failed.'],
      [/Vakáció mód hiba\. Próbáld újra\./g, 'Vacation mode error. Try again.'],
      [/Nincs ajánlói kód\./g, 'No referral code available.'],
      [/Ajánlói link másolva\./g, 'Referral link copied.'],
      [/Ajánlói kód másolva\./g, 'Referral code copied.'],
      [/Támogatóknak/g, 'For supporters'],
      [/Cégeknek/g, 'For companies'],
      [/Csatlakozási lehetőségek/g, 'Partnership options'],
      [/Adományautomata/g, 'Donation kiosk'],
      [/Civilek/g, 'For NGOs'],
      [/Digitális Kártya/g, 'Digital Card'],
      [/Rólunk/g, 'About us'],
      [/Médiamegjelenések/g, 'Media appearances'],
      [/Jog\b/g, 'Legal'],
      [/Hibabejelentés/g, 'Bug report'],
      [/SEGÍTEK/g, 'HELP'],
      [/Adományozok/g, 'Donate'],
      [/Profil\b/g, 'Profile'],
      [/Magyarország/g, 'Hungary'],
      [/\bMagyar\b/g, 'Hungarian'],
      [/Adatkezelési tájékoztató/g, 'Privacy Notice'],
      [/ÁSZF/g, 'Terms and Conditions'],
      [/Gyermekvédelmi szabályzat/g, 'Child Protection Policy'],
      [/Sajtótájékoztató/g, 'Press information'],
      [/Adataim törlésének kérése/g, 'Request deletion of my data'],
      [/Jótékonysági szervezet vagy\?/g, 'Are you a charity organisation?'],
      [/Letöltenéd az appunkat\?/g, 'Would you like to download our app?'],
      [/Megtalálsz minket is:/g, 'You can also find us here:'],
      [/Kattints ide!/g, 'Click here!'],
      [/Minden jog fenntartva\./g, 'All rights reserved.'],
      [/Másolás \(kód\)/g, 'Copy code'],
      [/Másolás/g, 'Copy'],
      [/Frissítés/g, 'Refresh'],
      [/Mentés…/g, 'Saving...'],
      [/Mentés/g, 'Save'],
      [/Minden vásárlás egy jó ügyet erősít\. Támogasd a kedvenc szervezeteidet anélkül, hogy többet fizetnél\./g, 'Every purchase supports a good cause. Support your favorite organizations without paying more.'],
      [/Válassz egy webshopot, egy akciós ajánlatot, vagy használd a kedvezménykuponok egyikét/g, 'Choose a webshop, a featured offer, or one of the discount coupons'],
      [/nincs más dolgot,/g, 'there is nothing else to do,'],
      [/kattints rá/g, 'click it'],
      [/ezen az oldalon/g, 'on this page'],
      [/és már vásárolhatsz is,/g, 'and start shopping,'],
      [/miközben adományt is gyűjtesz/g, 'while collecting donations'],
      [/Csak az innen indított vásárlás számít\./g, 'Only purchases started from this page count.'],
      [/Egy kattintással megoszthatod a kedvenc platformjaidon, így segíthetsz eljuttatni egy jó ügyet, minél több emberhez\./g, 'With one click you can share it on your favorite platforms and help a good cause reach more people.'],
      [/Válaszd ki, hol szeretnél vásárolni ! Kattints a webshop logóra, ajánlatra, kuponra\. Adományszerzéshez innen kell indítanod a vásárlást\./g, 'Choose where you want to shop. Click the webshop logo, offer, or coupon. To collect donations, you need to start your purchase from here.'],
      [/Ha nem egy szervezettől érkeztél ide, akkor a következő lépés a szervezet kiválasztása a listából\./g, 'If you did not arrive here from an organization page, the next step is to choose an organization from the list.'],
      [/Nincs más dolgod, mint a hagyományos módon vásárolni a webshopban\. Adományod meg fog jelenni a "Kik támogattak mostanában \?" listában, amit meg is oszthatsz\./g, 'There is nothing else to do but shop in the webshop as usual. Your donation will appear in the "Who supported recently?" list, which you can also share.'],
      [/Lejárat: folyamatos/g, 'Expires: ongoing'],
      [/Lejár:\s*/g, 'Expires: '],
      [/Rendben/g, 'OK']
    ];

    for (var i = 0; i < replacements.length; i += 1) {
      translated = translated.replace(replacements[i][0], replacements[i][1]);
    }

    translated = translated.replace(/(\d[\d\s.,]*)\s+pont a\s+([A-Za-z]+)\s+szinthez/g, '$1 points to $2');
    translated = translated.replace(/(\d{4})\.\s*(jan\.|febr\.|márc\.|ápr\.|máj\.|jún\.|júl\.|aug\.|szept\.|okt\.|nov\.|dec\.)\s*(\d{2})\./g, function(_, year, month, day) {
      return (monthMap[month] || month) + ' ' + day + ', ' + year;
    });
    translated = translated.replace(/(\d[\d\s.,]*)\s+pont\b/g, function(_, num) {
      return num + ' ' + singularOrPlural(num, 'point', 'points');
    });
    translated = translated.replace(/(\d[\d\s.,]*)\s+szavazat\b/g, function(_, num) {
      return num + ' ' + singularOrPlural(num, 'vote', 'votes');
    });
    translated = translated.replace(/(\d[\d\s.,]*)\s+nap\b/g, function(_, num) {
      return num + ' ' + singularOrPlural(num, 'day', 'days');
    });

    translated = translateUsdShopCurrencyText(translated);

    return translated;
  }

  // P2o: known static copy in the full profile. Auth/status controls and
  // monetary amounts require their separate localization packages.
  var PROFILE_COPY = {
  "Vissza a JYSK játékhoz": ["Back to the JYSK game", "Zurück zum JYSK-Spiel", "Retour au jeu JYSK"],
  "Adatok frissítése": ["Refresh profile data", "Profildaten aktualisieren", "Actualiser les données du profil"],
  "Meglévő belépési kód mentése (opcionális)": ["Save an existing login code (optional)", "Vorhandenen Anmeldecode speichern (optional)", "Enregistrer un code de connexion existant (facultatif)"],
  "Új belépési kód készül. Ha volt korábbi kódod, az érvénytelenné válik. Folytatod?": [
    "A new login code will be created. Any previous code will become invalid. Continue?",
    "Ein neuer Anmeldecode wird erstellt. Ein bisheriger Code wird ungültig. Fortfahren?",
    "Un nouveau code de connexion sera créé. Tout ancien code deviendra invalide. Continuer ?"
  ],
  "Nem kell saját jelszót kitalálnod. A kapott belépési kód a profilod kulcsa: a következő oldalon mentsd el a jelszókezelődbe vagy őrizd meg biztonságosan.": [
    "You do not need to invent a password. The generated login code is the key to your profile: save it in your password manager on the next page or keep it somewhere safe.",
    "Du musst kein Passwort erfinden. Der erzeugte Anmeldecode ist der Schlüssel zu deinem Profil: Speichere ihn auf der nächsten Seite im Passwortmanager oder bewahre ihn sicher auf.",
    "Vous n’avez pas à inventer un mot de passe. Le code généré est la clé de votre profil : enregistrez-le dans votre gestionnaire de mots de passe sur la page suivante ou conservez-le en lieu sûr."
  ],
  "Első látogatáskor automatikusan kapsz azonosítót. A frissítés a böngészőben megőrzött profil adatait tölti újra, nem másik azonosító kérésére szolgál. A profil megőrzéséhez mentsd el a belépési kódodat.": [
    "You receive an identifier automatically on your first visit. Refresh reloads the profile kept in this browser; it does not request a different identifier. Save your login code to keep access to your profile.",
    "Beim ersten Besuch erhältst du automatisch eine Kennung. Aktualisieren lädt das in diesem Browser gespeicherte Profil neu und fordert keine andere Kennung an. Speichere deinen Anmeldecode, um den Zugriff zu behalten.",
    "Un identifiant est créé automatiquement lors de votre première visite. Actualiser recharge le profil conservé dans ce navigateur sans demander un autre identifiant. Sauvegardez votre code de connexion pour conserver l’accès à votre profil."
  ],
  "Az impactod egy helyen": [
    "Your impact in one place",
    "Dein Impact an einem Ort",
    "Votre impact en un seul endroit"
  ],
  "Itt találod a profilodat, a pontjaidat és az elkölthető szavazataidat. A fiókod automatikusan létrejön, neked csak a belépési kódodat érdemes elmentened.": [
    "Here you'll find your profile, your points, and your spendable votes. Your account is created automatically; you just need to save your sign-in code.",
    "Hier findest du dein Profil, deine Punkte und deine verfügbaren Stimmen. Dein Konto wird automatisch erstellt; du musst nur deinen Anmeldecode speichern.",
    "Vous trouverez ici votre profil, vos points et vos votes disponibles. Votre compte est créé automatiquement ; il vous suffit de sauvegarder votre code de connexion."
  ],
  "Profilod": [
    "Your profile",
    "Dein Profil",
    "Votre profil"
  ],
  "Becenév": [
    "Nickname",
    "Spitzname",
    "Pseudo"
  ],
  "Értesítések": [
    "Notifications",
    "Benachrichtigungen",
    "Notifications"
  ],
  "A profil üzeneteket pushban is elküldjük (ha engedélyezed).": [
    "We also send profile messages via push (if you allow it).",
    "Wir senden Profilnachrichten auch per Push (wenn du es erlaubst).",
    "Nous envoyons également les messages de profil par push (si vous l'autorisez)."
  ],
  "Azonosítód": [
    "Your ID",
    "Deine ID",
    "Votre ID"
  ],
  "Másolás": [
    "Copy",
    "Kopieren",
    "Copier"
  ],
  "Frissítés": [
    "Refresh",
    "Aktualisieren",
    "Actualiser"
  ],
  "Ezzel kapcsoljuk össze az adományt és a jutalmakat.": [
    "This links your donation and your rewards.",
    "So verbinden wir Spende und Belohnungen.",
    "Cela relie votre don et vos récompenses."
  ],
  "A belépési kód csak külön, egyszeri biztonságos oldalon jelenik meg. Mentsd el a jelszókezelődbe.": [
    "Your sign-in code is shown only once, on a separate secure page. Save it in your password manager.",
    "Dein Anmeldecode erscheint nur einmal auf einer separaten sicheren Seite. Speichere ihn in deinem Passwortmanager.",
    "Votre code de connexion n'apparaît qu'une seule fois, sur une page sécurisée distincte. Enregistrez-le dans votre gestionnaire de mots de passe."
  ],
  "Mentés jelszókezelőbe (opcionális)": [
    "Save to password manager (optional)",
    "In Passwortmanager speichern (optional)",
    "Enregistrer dans le gestionnaire de mots de passe (facultatif)"
  ],
  "Azonosító": [
    "ID",
    "ID",
    "ID"
  ],
  "Belépési kód": [
    "Sign-in code",
    "Anmeldecode",
    "Code de connexion"
  ],
  "Mentés": [
    "Save",
    "Speichern",
    "Enregistrer"
  ],
  "A böngésző felajánlhatja a mentést.": [
    "Your browser may offer to save it.",
    "Dein Browser bietet möglicherweise an, ihn zu speichern.",
    "Votre navigateur peut proposer de l'enregistrer."
  ],
  "Becenév (opcionális)": [
    "Nickname (optional)",
    "Spitzname (optional)",
    "Pseudo (facultatif)"
  ],
  "pl. Anna": [
    "e.g. Anna",
    "z. B. Anna",
    "p. ex. Anna"
  ],
  "Szinted és pontjaid": [
    "Your level and points",
    "Dein Level und deine Punkte",
    "Votre niveau et vos points"
  ],
  "Miért éri meg?": [
    "Why is it worth it?",
    "Warum lohnt es sich?",
    "Pourquoi est-ce intéressant ?"
  ],
  "A magasabb szint nagyobb adománybónuszt és előnyöket ad. A pontküszöbök tájékoztató jellegűek, a szinteloszlás percentilis alapján is alakul.": [
    "A higher level gives a bigger donation bonus and more benefits. Point thresholds are indicative; level distribution also depends on percentiles.",
    "Ein höheres Level bringt einen größeren Spendenbonus und mehr Vorteile. Punktschwellen sind Richtwerte; die Levelverteilung hängt auch von Perzentilen ab.",
    "Un niveau plus élevé offre un bonus de don plus important et plus d'avantages. Les seuils de points sont indicatifs ; la répartition des niveaux dépend également des percentiles."
  ],
  "Elkölthető szavazatok": [
    "Spendable votes",
    "Verfügbare Stimmen",
    "Votes disponibles"
  ],
  "Legutóbbi aktivitás": [
    "Recent activity",
    "Letzte Aktivität",
    "Activité récente"
  ],
  "Még nincs aktivitás.": [
    "No activity yet.",
    "Noch keine Aktivität.",
    "Aucune activité pour le moment."
  ],
  "Ajánlás": [
    "Referral",
    "Empfehlung",
    "Parrainage"
  ],
  "Infó": [
    "Info",
    "Info",
    "Info"
  ],
  "Oszd meg a kódot, hogy jutalmat kapjatok.": [
    "Share the code to earn rewards together.",
    "Teile den Code, um gemeinsam Belohnungen zu erhalten.",
    "Partagez le code pour obtenir des récompenses ensemble."
  ],
  "Még nincs jelvényed.": [
    "You have no badges yet.",
    "Du hast noch keine Abzeichen.",
    "Vous n'avez pas encore de badges."
  ],
  "Belépés meglévő fiókba": [
    "Sign in to an existing account",
    "Mit bestehendem Konto anmelden",
    "Se connecter à un compte existant"
  ],
  "A fiókot automatikusan létrehoztuk; az adataidat a pseudo ID és a belépési kód kapcsolja össze. Ha másik eszközön már létrehoztad, itt léphetsz be. Az elvesztett belépési kód másik ellenőrzött azonosító nélkül nem állítható helyre.": [
    "Your account was created automatically; your data is linked by the pseudo ID and the sign-in code. If you created it on another device, you can sign in here. A lost sign-in code cannot be recovered without another verified ID.",
    "Dein Konto wurde automatisch erstellt; deine Daten werden über die Pseudo-ID und den Anmeldecode verknüpft. Wenn du es auf einem anderen Gerät erstellt hast, kannst du dich hier anmelden. Ein verlorener Anmeldecode kann ohne eine andere verifizierte ID nicht wiederhergestellt werden.",
    "Votre compte a été créé automatiquement ; vos données sont reliées par l'ID pseudo et le code de connexion. Si vous l'avez créé sur un autre appareil, vous pouvez vous connecter ici. Un code de connexion perdu ne peut pas être récupéré sans autre ID vérifié."
  ]
};
  var profileCopySources;
  var PROFILE_STATIC_SELECTORS = [
    '.impactshop-profile-intro > h2', '.impactshop-profile-intro > p',
    '.impactshop-identity-header > h3', '.impactshop-identity-summary__label',
    '.impactshop-identity-block > h4', '.impactshop-identity-block > label',
    '.impactshop-identity-block > .impactshop-identity-hint:not([data-role])',
    '.impactshop-identity-save > label', '.impactshop-identity-save > p',
    '.impactshop-identity-restore > h4', '.impactshop-identity-restore > label',
    '#impactshop-signin-help', '[data-role="points-info"] > p',
    '[data-role="referral-info"] > p', '[data-role="points-history-empty"]',
    '[data-role="badges-empty"]', '[data-role="copy-pseudo"]',
    '[data-role="refresh-profile"]', '[data-role="save-password-manager"]',
    '[data-role="points-info-trigger"]', '[data-role="referral-copy"]',
    '[data-role="referral-info-trigger"]', '[data-role="jysk-return"]'
  ].join(',');

  function profileCopyLanguage() {
    var bar = document.querySelector('.sharity-action-bar[data-sharity-preferences-managed]');
    var language = bar && bar.getAttribute('lang');
    return ['hu', 'en', 'de', 'fr'].indexOf(language) !== -1 ? language : null;
  }

  function profileStaticText(text, language) {
    if (!profileCopySources) {
      profileCopySources = Object.create(null);
      Object.keys(PROFILE_COPY).forEach(function(source) {
        // The legacy overlay may have run before a late-mounted selector.
        [source, translateDynamicText(source)].concat(PROFILE_COPY[source]).forEach(function(value) {
          profileCopySources[normalizeText(value)] = source;
        });
      });
    }
    var source = profileCopySources[normalizeText(text)];
    if (!source) return null;
    var translated = language === 'hu' ? source : PROFILE_COPY[source][['en', 'de', 'fr'].indexOf(language)];
    return text.replace(text.trim(), translated);
  }

  function applyProfileCopy() {
    var language = profileCopyLanguage();
    if (!language) return; // No new selector: retain the original page behavior.
    var roots = document.querySelectorAll('.impactshop-profile-shell');
    if (roots.length && !document.getElementById('sharity-profile-copy-style')) {
      var style = document.createElement('style');
      style.id = 'sharity-profile-copy-style';
      // This text button shares the legacy 22px icon-button class.
      style.textContent = '.impactshop-profile-shell [data-role="points-info-trigger"][data-sharity-profile-copy]{min-width:fit-content;max-width:100%;height:auto}' +
        '.impactshop-profile-intro > [data-sharity-profile-copy]{position:relative;z-index:1}';
      document.head.appendChild(style);
    }
    roots.forEach(function(root) {
      root.querySelectorAll('[data-code-confirm]').forEach(function(el) {
        var value = el.getAttribute('data-code-confirm');
        var text = profileStaticText(value, language);
        if (text !== null && text !== value) el.setAttribute('data-code-confirm', text);
      });
      root.querySelectorAll(PROFILE_STATIC_SELECTORS).forEach(function(el) {
        var known = false;
        // Do not replace elements: the restore heading contains its own info button.
        Array.prototype.forEach.call(el.childNodes, function(node) {
          if (node.nodeType !== 3) return;
          var text = profileStaticText(node.nodeValue, language);
          if (text === null) return;
          known = true;
          if (node.nodeValue !== text) node.nodeValue = text;
        });
        if (known) {
          if (!el.hasAttribute('data-sharity-profile-copy')) el.setAttribute('data-sharity-profile-copy', '');
          if (el.lang !== language) el.lang = language;
        }
      });
      root.querySelectorAll('input[data-role="save-username"], input[data-role="save-password"], input[data-role="nickname-input"], input[data-role="restore-pseudo"], input[data-role="restore-recovery"]').forEach(function(el) {
        var value = el.getAttribute('placeholder');
        if (value === null) return;
        var text = profileStaticText(value, language);
        if (text !== null && text !== value) el.setAttribute('placeholder', text);
        if (text !== null && el.lang !== language) el.lang = language;
      });
    });
  }

  // P2p: render only known messages in named full-profile fields. Capture values
  // as strings, never parse points/votes or touch a provider amount.
  var PROFILE_DYNAMIC_COPY = {
    "messageAcknowledge": ["Rendben", "OK", "OK", "OK"],
    // P2r: date/name slots stay opaque; only known display labels change.
    history_impact_shop_purchase: ["Impact Shop vásárlás{0}", "Impact Shop purchase{0}", "Impact-Shop-Kauf{0}", "Achat Impact Shop{0}"],
    history_first_purchase: ["Első vásárlás{0}", "First purchase{0}", "Erster Kauf{0}", "Premier achat{0}"],
    history_shop_discovery: ["Shop felfedezés{0}", "Shop discovery{0}", "Shop-Entdeckung{0}", "Découverte du shop{0}"],
    history_ads_video: ["Ads videó{0}", "Ads video{0}", "Werbevideo{0}", "Vidéo d’annonces{0}"],
    history_sponsored_video: ["Szponzori videó{0}", "Sponsored video{0}", "Sponsor-Video{0}", "Vidéo sponsorisée{0}"],
    history_share: ["Megosztás{0}", "Share{0}", "Teilen{0}", "Partage{0}"],
    history_feedback: ["Visszajelzés{0}", "Feedback{0}", "Feedback{0}", "Retour d’information{0}"],
    history_referral: ["Ajánlás{0}", "Referral{0}", "Empfehlung{0}", "Parrainage{0}"],
    history_referral_bonus: ["Ajánlói bónusz{0}", "Referral bonus{0}", "Empfehlungsbonus{0}", "Bonus de parrainage{0}"],
    history_profile_complete: ["Profil kitöltése{0}", "Profile completion{0}", "Profil ausfüllen{0}", "Remplissage du profil{0}"],
    history_nickname: ["Becenév mentés{0}", "Nickname saved{0}", "Spitzname gespeichert{0}", "Pseudo enregistré{0}"],
    history_daily_login: ["Napi belépés{0}", "Daily login{0}", "Tägliche Anmeldung{0}", "Connexion quotidienne{0}"],
    history_streak_bonus: ["Sorozat bónusz{0}", "Streak bonus{0}", "Serienbonus{0}", "Bonus de série{0}"],
    history_wallet_download: ["Wallet letöltés{0}", "Wallet download{0}", "Wallet-Download{0}", "Téléchargement du wallet{0}"],
    history_tombola: ["Digitális tombola{0}", "Digital tombola{0}", "Digitale Tombola{0}", "Tombola numérique{0}"],
    history_bonus: ["Bónusz{0}", "Bonus{0}", "Bonus{0}", "Bonus{0}"],
    history_admin_adjustment: ["Pontkorrekció{0}", "Point correction{0}", "Punktkorrektur{0}", "Correction de points{0}"],
    history_vacation: ["Vakáció mód{0}", "Vacation mode{0}", "Urlaubsmodus{0}", "Mode vacances{0}"],
    history_inactivity: ["Inaktivitás{0}", "Inactivity{0}", "Inaktivität{0}", "Inactivité{0}"],
    history_point_movement: ["Pontmozgás{0}", "Point movement{0}", "Punktbewegung{0}", "Mouvement de points{0}"],
    pushToggle_on: ["Értesítések bekapcsolása", "Turn notifications on", "Benachrichtigungen aktivieren", "Activer les notifications"],
    pushToggle_off: ["Értesítések kikapcsolása", "Turn notifications off", "Benachrichtigungen deaktivieren", "Désactiver les notifications"],
    pushToggle_denied: ["Értesítések tiltva", "Notifications blocked", "Benachrichtigungen blockiert", "Notifications bloquées"],
    pushStatus_swUnavailable: ["A push nem elérhető (SW).", "Push notifications are unavailable.", "Push-Benachrichtigungen sind nicht verfügbar.", "Les notifications push sont indisponibles."],
    pushStatus_browserBlocked: ["A böngészőben le vannak tiltva az értesítések.", "Notifications are blocked in your browser.", "Benachrichtigungen sind im Browser blockiert.", "Les notifications sont bloquées dans votre navigateur."],
    pushStatus_active: ["Push értesítések aktívak.", "Push notifications are active.", "Push-Benachrichtigungen sind aktiv.", "Les notifications push sont actives."],
    pushStatus_loginRequired: ["A push módosításához biztonságos kapcsolódás szükséges.", "Sign in securely to change notification settings.", "Melde dich sicher an, um die Benachrichtigungseinstellungen zu ändern.", "Connectez-vous de manière sécurisée pour modifier les notifications."],
    pushStatus_disabled: ["Értesítések kikapcsolva.", "Notifications turned off.", "Benachrichtigungen deaktiviert.", "Notifications désactivées."],
    pushStatus_permissionNeeded: ["Az értesítések engedélyezése szükséges.", "Notification permission is required.", "Berechtigung für Benachrichtigungen erforderlich.", "L’autorisation de notification est requise."],
    pushStatus_enabled: ["Értesítések bekapcsolva.", "Notifications turned on.", "Benachrichtigungen aktiviert.", "Notifications activées."],
    pushStatus_enabledSafari: ["Értesítések bekapcsolva. Safari/iOS esetén a telepített Sharity appban jelennek meg.", "Notifications turned on. On Safari/iOS they appear in the installed Sharity app.", "Benachrichtigungen aktiviert. Auf Safari/iOS erscheinen sie in der installierten Sharity-App.", "Notifications activées. Sur Safari/iOS, elles apparaissent dans l’application Sharity installée."],
    pushStatus_failed: ["Nem sikerült bekapcsolni.", "Could not turn on.", "Aktivieren fehlgeschlagen.", "Impossible d’activer."],
    lastNgo_empty: ["Jelenleg nincs eltárolt NGO választásod.", "No stored NGO choice yet.", "Noch keine gespeicherte NGO-Auswahl.", "Aucun choix d’ONG enregistré pour le moment."],
    lastNgo_error: ["Nem sikerült betölteni a legutóbbi NGO-t.", "Could not load your latest NGO.", "Die letzte NGO konnte nicht geladen werden.", "Impossible de charger votre dernière ONG."],
    lastNgo_chooseButton: ["Másikat választok", "Choose another", "Andere wählen", "Choisir une autre"],
    lastNgo_name: ["Legutóbb ezt a szervezetet támogattad: {0}", "Most recently you supported: {0}", "Zuletzt hast du diese Organisation unterstützt: {0}", "Dernièrement, vous avez soutenu : {0}"],
    // P2q: exact UI strings for profile save, sign-in and code reveal.
    // Own the shared status field without losing its existing local messages.
    profileStatus1: ["A kiválasztott civil ügy aktiválása folyamatban…", "Activating your selected cause…", "Dein gewähltes Anliegen wird aktiviert…", "Activation de la cause sélectionnée…"],
    profileStatus2: ["A civil ügy aktiválása most nem sikerült.", "The cause could not be activated.", "Das Anliegen konnte nicht aktiviert werden.", "La cause n’a pas pu être activée."],
    profileStatus3: ["A módosításhoz előbb biztonságosan kapcsolódj a fiókodhoz.", "Connect securely to your account before making changes.", "Verbinde dich sicher mit deinem Konto, bevor du Änderungen vornimmst.", "Connectez-vous à votre compte de manière sécurisée avant toute modification."],
    profileStatus4: ["Nem sikerült azonosítót kérni. Próbáld újra.", "Could not retrieve your ID. Please try again.", "Deine ID konnte nicht abgerufen werden. Versuche es erneut.", "Impossible de récupérer votre identifiant. Réessayez."],
    profileStatus5: ["A profil most nem érhető el. Próbáld újra később.", "Your profile is currently unavailable. Please try again later.", "Dein Profil ist derzeit nicht verfügbar. Versuche es später erneut.", "Votre profil est indisponible pour le moment. Réessayez plus tard."],
    profileStatus6: ["Azonosító betöltve.", "Identity loaded.", "ID geladen.", "Identifiant chargé."],
    profileStatus7: ["Frissítés folyamatban…", "Refreshing…", "Wird aktualisiert…", "Actualisation en cours…"],
    profileStatus8: ["Nincs aktív azonosító.", "No active ID.", "Keine aktive ID.", "Aucun identifiant actif."],
    profileStatus9: ["Azonosító másolva.", "ID copied.", "ID kopiert.", "Identifiant copié."],
    profileStatus10: ["Másolás sikertelen.", "Copy failed.", "Kopieren fehlgeschlagen.", "Échec de la copie."],
    profileStatus11: ["Nincs aktív kód.", "No active code.", "Kein aktiver Code.", "Aucun code actif."],
    profileStatus12: ["Belépési kód másolva.", "Login code copied.", "Anmeldecode kopiert.", "Code de connexion copié."],
    profileStatus13: ["Megosztás indítása…", "Starting sharing…", "Teilen wird gestartet…", "Démarrage du partage…"],
    profileStatus14: ["Nincs aktív azonosító vagy kód.", "No active ID or code.", "Keine aktive ID oder kein aktiver Code.", "Aucun identifiant ou code actif."],
    profileStatus15: ["Megosztva.", "Shared.", "Geteilt.", "Partagé."],
    profileStatus16: ["Megosztás megszakítva.", "Sharing cancelled.", "Teilen abgebrochen.", "Partage annulé."],
    profileStatus17: ["Megosztás helyett másolva.", "Copied instead of shared.", "Kopiert statt geteilt.", "Copié au lieu d’être partagé."],
    profileStatus18: ["Másold ki az adatokat a felugró ablakból.", "Copy the details from the popup.", "Kopiere die Daten aus dem Popup.", "Copiez les données depuis la fenêtre contextuelle."],
    profileStatus19: ["Mentve a jelszókezelőbe.", "Saved to your password manager.", "Im Passwortmanager gespeichert.", "Enregistré dans le gestionnaire de mots de passe."],
    profileStatus20: ["A mentéshez előbb biztonságosan kapcsolódj a fiókodhoz.", "Connect securely to your account before saving.", "Verbinde dich sicher mit deinem Konto, bevor du speicherst.", "Connectez-vous à votre compte de manière sécurisée avant d’enregistrer."],
    profileStatus21: ["A mentéshez előbb nyisd meg a biztonságos belépési kódoldalt.", "Open the secure login code page before saving.", "Öffne vor dem Speichern die sichere Anmeldecode-Seite.", "Ouvrez la page sécurisée du code de connexion avant d’enregistrer."],
    profileStatus22: ["A mentés ezen a nézeten nem érhető el.", "Saving is unavailable in this view.", "Speichern ist in dieser Ansicht nicht verfügbar.", "L’enregistrement est indisponible dans cette vue."],
    profileStatus23: ["Jelszókezelő mentés indítva. Visszaléptetünk…", "Password manager save started. Taking you back…", "Speichern im Passwortmanager gestartet. Wir leiten dich zurück…", "Enregistrement dans le gestionnaire de mots de passe lancé. Retour en cours…"],
    profileStatus24: ["Jelszókezelő mentés indítva.", "Password manager save started.", "Speichern im Passwortmanager gestartet.", "Enregistrement dans le gestionnaire de mots de passe lancé."],
    profileStatus25: ["Vakáció kikapcsolása sikertelen.", "Failed to turn off vacation mode.", "Urlaubsmodus konnte nicht deaktiviert werden.", "Impossible de désactiver le mode vacances."],
    profileStatus26: ["Vakáció indítása sikertelen.", "Failed to start vacation mode.", "Urlaubsmodus konnte nicht gestartet werden.", "Impossible d’activer le mode vacances."],
    profileStatus27: ["Vakáció mód hiba. Próbáld újra.", "Vacation mode error. Please try again.", "Fehler im Urlaubsmodus. Versuche es erneut.", "Erreur du mode vacances. Réessayez."],
    profileStatus28: ["Nincs ajánlói kód.", "No referral code.", "Kein Empfehlungscode.", "Aucun code de parrainage."],
    profileStatus29: ["Ajánlói link másolva.", "Referral link copied.", "Empfehlungslink kopiert.", "Lien de parrainage copié."],
    profileStatus30: ["Ajánlói kód másolva.", "Referral code copied.", "Empfehlungscode kopiert.", "Code de parrainage copié."],
    statusLoginRequiredForCode: ["A belépési kódhoz előbb biztonságosan kapcsolódj a fiókodhoz.", "Connect securely to your account before requesting a login code.", "Verbinde dich zuerst sicher mit deinem Konto, bevor du einen Anmeldecode anforderst.", "Connectez-vous d'abord en toute sécurité à votre compte avant de demander un code de connexion."],
    codeGenerating: ["Kód készítése…", "Generating code…", "Code wird erstellt…", "Génération du code…"],
    codeGenerationFailed: ["A belépési kód létrehozása nem sikerült.", "Failed to create the login code.", "Der Anmeldecode konnte nicht erstellt werden.", "Échec de la création du code de connexion."],
    codePageOpened: ["A biztonságos kódoldal megnyílt.", "The secure code page has opened.", "Die sichere Codeseite wurde geöffnet.", "La page de code sécurisée s'est ouverte."],
    restoreLoginRequired: ["Belépés szükséges.", "Login required.", "Anmeldung erforderlich.", "Connexion requise."],
    invalidPseudo: ["Adj meg érvényes azonosítót.", "Enter a valid identifier.", "Gib eine gültige Kennung ein.", "Saisissez un identifiant valide."],
    invalidRecoveryFormat: ["Érvénytelen belépési kód formátum.", "Invalid login code format.", "Ungültiges Format des Anmeldecodes.", "Format de code de connexion invalide."],
    loginInProgress: ["Belépés folyamatban…", "Signing in…", "Anmeldung läuft…", "Connexion en cours…"],
    loginInProgressLabel: ["Belépés…", "Signing in…", "Anmeldung…", "Connexion…"],
    loginFailed: ["Belépés sikertelen.", "Sign-in failed.", "Anmeldung fehlgeschlagen.", "Échec de la connexion."],
    loginSuccess: ["Belépés sikeres.", "Signed in successfully.", "Erfolgreich angemeldet.", "Connexion réussie."],
    loginSuccessRedirecting: ["Belépés sikeres. Visszaléptetünk…", "Signed in successfully. Taking you back…", "Erfolgreich angemeldet. Wir leiten dich zurück…", "Connexion réussie. Retour en cours…"],
    loginError: ["Belépési hiba.", "Sign-in error.", "Anmeldefehler.", "Erreur de connexion."],
    nicknameLoginRequired: ["A becenév módosításához lépj be a meglévő fiókodba.", "Sign in to your existing account to change your nickname.", "Melde dich in deinem bestehenden Konto an, um deinen Spitznamen zu ändern.", "Connectez-vous à votre compte existant pour modifier votre pseudo."],
    nicknameSaving: ["Becenév mentése…", "Saving nickname…", "Spitzname wird gespeichert…", "Enregistrement du pseudo…"],
    nicknameSaveFailed: ["Becenév mentése sikertelen.", "Failed to save nickname.", "Spitzname konnte nicht gespeichert werden.", "Échec de l'enregistrement du pseudo."],
    nicknameSaveFailedStatus: ["Sikertelen mentés.", "Save failed.", "Speichern fehlgeschlagen.", "Échec de l'enregistrement."],
    nicknameSaveError: ["Becenév mentése hiba.", "Error saving nickname.", "Fehler beim Speichern des Spitznamens.", "Erreur lors de l'enregistrement du pseudo."],
    nicknameSaveErrorStatus: ["Hiba történt.", "An error occurred.", "Ein Fehler ist aufgetreten.", "Une erreur s'est produite."],
    nicknameSaved: ["Becenév mentve.", "Nickname saved.", "Spitzname gespeichert.", "Pseudo enregistré."],
    nicknameSavingLabel: ["Mentés…", "Saving…", "Speichern…", "Enregistrement…"],
    nicknameSavedLabel: ["Mentve", "Saved", "Gespeichert", "Enregistré"],
    nicknameSaveLabel: ["Mentés", "Save", "Speichern", "Enregistrer"],
    restoreLabel: ["Belépés", "Sign in", "Anmelden", "Se connecter"],
    codeRevealLabel: ["Új belépési kód készítése", "Create a new login code", "Neuen Anmeldecode erstellen", "Créer un nouveau code de connexion"],
    codeReload: ["A kód készítéséhez töltsd újra a profiloldalt.", "Reload the profile page to create a code.", "Lade die Profilseite neu, um einen Code zu erstellen.", "Rechargez la page du profil pour créer un code."],
    codeMissingForSave: ["Add meg a már elmentett belépési kódodat, vagy külön kérj új kódot a profiloldalon.", "Enter your saved login code, or separately request a new code on the profile page.", "Gib deinen gespeicherten Anmeldecode ein oder fordere auf der Profilseite separat einen neuen Code an.", "Saisissez votre code enregistré ou demandez séparément un nouveau code sur la page du profil."],
    profileRefreshed: ["Profiladatok frissítve.", "Profile refreshed.", "Profil aktualisiert.", "Profil actualisé."],
    profileRefreshFailed: ["Nem sikerült frissíteni a profiladatokat. Próbáld újra.", "Could not refresh your profile. Please try again.", "Das Profil konnte nicht aktualisiert werden. Versuche es erneut.", "Impossible d’actualiser le profil. Réessayez."],
  "greetingWithName": [
    "Szia {0}! Üdvözöllek a Sharity oldalán.",
    "Hi {0}! Welcome to Sharity.",
    "Hallo {0}! Willkommen bei Sharity.",
    "Bonjour {0} ! Bienvenue sur Sharity."
  ],
  "greetingWithoutName": [
    "Szia, üdvözöllek a Sharity oldalán.",
    "Hi, welcome to Sharity.",
    "Hallo, willkommen bei Sharity.",
    "Bonjour, bienvenue sur Sharity."
  ],
  "votesLoginRequired": [
    "Belépés szükséges",
    "Login required",
    "Anmeldung erforderlich",
    "Connexion requise"
  ],
  "votesExpired": [
    "Kapcsolat lejárt",
    "Connection expired",
    "Verbindung abgelaufen",
    "Connexion expirée"
  ],
  "votesBindingPending": [
    "Kapcsolás folyamatban…",
    "Binding in progress…",
    "Verbindung wird hergestellt…",
    "Liaison en cours…"
  ],
  "votesUnavailable": [
    "Átmenetileg nem elérhető",
    "Temporarily unavailable",
    "Vorübergehend nicht verfügbar",
    "Temporairement indisponible"
  ],
  "votesStateActive": [
    "A jelenlegi, elkölthető egyenleged.",
    "Your currently available votes.",
    "Deine derzeit verfügbaren Stimmen.",
    "Vos votes actuellement disponibles."
  ],
  "votesStateLegacyReadOnly": [
    "A szavazategyenleghez lépj be a meglévő fiókodba.",
    "Sign in to your existing account to see your available votes.",
    "Melde dich bei deinem bestehenden Konto an, um deine verfügbaren Stimmen zu sehen.",
    "Connectez-vous à votre compte existant pour voir vos votes disponibles."
  ],
  "votesStateInvalidOrRevoked": [
    "A biztonságos kapcsolat lejárt. Újbóli belépés szükséges.",
    "Your secure connection expired. Please log in again.",
    "Deine sichere Verbindung ist abgelaufen. Bitte melde dich erneut an.",
    "Votre connexion sécurisée a expiré. Veuillez vous reconnecter."
  ],
  "votesStateBindingPending": [
    "A biztonságos eszközkapcsolás még folyamatban van.",
    "The secure device binding is still in progress.",
    "Die sichere Geräteverknüpfung läuft noch.",
    "La liaison sécurisée de l’appareil est toujours en cours."
  ],
  "votesStateUnavailable": [
    "A profiladatok most nem érhetők el. Próbáld újra később.",
    "Your profile data is currently unavailable. Please try again later.",
    "Deine Profildaten sind derzeit nicht verfügbar. Bitte versuche es später erneut.",
    "Vos données de profil sont actuellement indisponibles. Veuillez réessayer plus tard."
  ],
  "pointsHighestLevel": [
    "Legmagasabb szint elérve.",
    "Highest level reached.",
    "Höchste Stufe erreicht.",
    "Niveau maximal atteint."
  ],
  "pointsProgressText": [
    "{0} pont a {1} szinthez",
    "Points to level {1}: {0}",
    "{0} Punkte bis Stufe {1}",
    "{0} points pour le niveau {1}"
  ],
  "benefitsDonationBonus": [
    "🎯 Adomány bónusz: ",
    "🎯 Donation bonus: ",
    "🎯 Spendenbonus: ",
    "🎯 Bonus de don : "
  ],
  "benefitsVoteWeight": [
    "🗳️ Szavazati súly: ",
    "🗳️ Vote weight: ",
    "🗳️ Stimmgewicht: ",
    "🗳️ Poids de vote : "
  ],
  "benefitsMaxDiscount": [
    "🏷️ Max kedvezményből: ",
    "🏷️ Max discount: ",
    "🏷️ Maximaler Rabatt: ",
    "🏷️ Remise maximale : "
  ],
  "vacationLoginRequired": [
    "A vakáció módosításához biztonságos kapcsolódás szükséges.",
    "A secure connection is required to modify vacation.",
    "Für die Änderung des Urlaubsmodus ist eine sichere Verbindung erforderlich.",
    "Une connexion sécurisée est requise pour modifier le mode vacances."
  ],
  "vacationActive": [
    "Vakáció aktív: {0} nap hátra.",
    "Vacation active. Days left: {0}.",
    "Urlaubsmodus aktiv. Verbleibende Tage: {0}.",
    "Mode vacances actif. Jours restants : {0}."
  ],
  "vacationInactive": [
    "Nincs aktív vakáció. Éves alkalmak: {0}",
    "No active vacation. Yearly activations: {0}",
    "Kein aktiver Urlaub. Jährliche Aktivierungen: {0}",
    "Mode vacances inactif. Activations annuelles : {0}"
  ],
  "vacationEnable": [
    "Bekapcsolás",
    "Enable",
    "Aktivieren",
    "Activer"
  ],
  "vacationDisable": [
    "Kikapcsolás",
    "Disable",
    "Deaktivieren",
    "Désactiver"
  ],
  "herowallTierLegacy": [
    "Legacy Pool helyezés: #{0}",
    "Legacy Pool position: #{0}",
    "Legacy-Pool-Platzierung: #{0}",
    "Position Legacy Pool : #{0}"
  ],
  "herowallTierLegacyNone": [
    "Legacy Pool helyezés: —",
    "Legacy Pool position: —",
    "Legacy-Pool-Platzierung: —",
    "Position Legacy Pool : —"
  ],
  "herowallPoints": [
    "Pontszám: {0} pont",
    "Score: {0} points",
    "Punktzahl: {0} Punkte",
    "Score : {0} points"
  ],
  "pointsTotal": [
    "{0} pont",
    "Points: {0}",
    "Punkte: {0}",
    "Points : {0}"
  ],
  "vacationLoading": [
    "Szabadság státusz betöltése…",
    "Loading vacation status…",
    "Urlaubsstatus wird geladen…",
    "Chargement du mode vacances…"
  ],
  "vacationMode": [
    "Szabadság mód",
    "Vacation mode",
    "Urlaubsmodus",
    "Mode vacances"
  ]
};
  var PROFILE_DYNAMIC_ROLES = {
    "account-message": ["messageAcknowledge"],
    "push-toggle": ["pushToggle_on", "pushToggle_off", "pushToggle_denied"],
    "push-status": ["pushStatus_swUnavailable", "pushStatus_browserBlocked", "pushStatus_active", "pushStatus_loginRequired", "pushStatus_disabled", "pushStatus_permissionNeeded", "pushStatus_enabled", "pushStatus_enabledSafari", "pushStatus_failed"],
    "last-ngo": ["lastNgo_empty", "lastNgo_error", "lastNgo_chooseButton", "lastNgo_name"],
    "points-history": ["history_impact_shop_purchase", "history_first_purchase", "history_shop_discovery", "history_ads_video", "history_sponsored_video", "history_share", "history_feedback", "history_referral", "history_referral_bonus", "history_profile_complete", "history_nickname", "history_daily_login", "history_streak_bonus", "history_wallet_download", "history_tombola", "history_bonus", "history_admin_adjustment", "history_vacation", "history_inactivity", "history_point_movement"],
    "generate-code": ["codeRevealLabel", "codeGenerating"],
    "restore-submit": ["restoreLabel", "loginInProgressLabel"],
    "save-nickname": ["nicknameSaveLabel", "nicknameSavingLabel", "nicknameSavedLabel"],
    "nickname-status": ["restoreLoginRequired", "nicknameSaveFailedStatus", "nicknameSaveErrorStatus", "nicknameSaved"],
    "restore-status": ["invalidPseudo", "invalidRecoveryFormat", "loginInProgress", "loginFailed", "loginSuccess", "loginSuccessRedirecting", "loginError"],
    "status": ["codeReload", "codeMissingForSave", "profileRefreshed", "profileRefreshFailed", "profileStatus1", "profileStatus2", "profileStatus3", "profileStatus4", "profileStatus5", "profileStatus6", "profileStatus7", "profileStatus8", "profileStatus9", "profileStatus10", "profileStatus11", "profileStatus12", "profileStatus13", "profileStatus14", "profileStatus15", "profileStatus16", "profileStatus17", "profileStatus18", "profileStatus19", "profileStatus20", "profileStatus21", "profileStatus22", "profileStatus23", "profileStatus24", "profileStatus25", "profileStatus26", "profileStatus27", "profileStatus28", "profileStatus29", "profileStatus30", "statusLoginRequiredForCode", "codeGenerationFailed", "codePageOpened", "invalidPseudo", "invalidRecoveryFormat", "loginInProgress", "loginFailed", "loginSuccess", "loginSuccessRedirecting", "loginError", "nicknameLoginRequired", "nicknameSaving", "nicknameSaveFailed", "nicknameSaved", "nicknameSaveError"],
    'greeting': ['greetingWithoutName', 'greetingWithName'],
    'votes-available': ['votesLoginRequired', 'votesExpired', 'votesBindingPending', 'votesUnavailable'],
    'votes-state': ['votesStateActive', 'votesStateLegacyReadOnly', 'votesStateInvalidOrRevoked', 'votesStateBindingPending', 'votesStateUnavailable'],
    'points-total': ['pointsTotal'],
    'points-progress-text': ['pointsHighestLevel', 'pointsProgressText'],
    'points-benefits': ['benefitsDonationBonus', 'benefitsVoteWeight', 'benefitsMaxDiscount'],
    'vacation-status': ['vacationLoading', 'vacationLoginRequired', 'vacationActive', 'vacationInactive'],
    'vacation-toggle': ['vacationMode', 'vacationEnable', 'vacationDisable'],
    'herowall-tier': ['herowallTierLegacyNone', 'herowallTierLegacy'],
    'herowall-points': ['herowallPoints']
  };
  // Existing EN overlay output must also be accepted when the selector mounts late.
  var PROFILE_DYNAMIC_ALIASES = {
    pointsTotal: ['{0} point', '{0} points'],
    pointsProgressText: ['{0} points to {1}'],
    vacationActive: ['Vacation active: {0} days left.'],
    vacationInactive: ['No active vacation. Yearly activations: {0}'],
    herowallPoints: ['Score: {0} point', 'Score: {0} points']
  };
  var profileDynamicMatchers;
  var profileDynamicLangOrigins = new WeakMap();

  function profileDynamicText(text, keys, language) {
    if (!profileDynamicMatchers) {
      profileDynamicMatchers = Object.create(null);
      Object.keys(PROFILE_DYNAMIC_COPY).forEach(function(key) {
        var forms = PROFILE_DYNAMIC_COPY[key].concat(PROFILE_DYNAMIC_ALIASES[key] || []);
        forms.push(translateDynamicText(PROFILE_DYNAMIC_COPY[key][0]));
        profileDynamicMatchers[key] = forms.map(function(template) {
          var slots = [];
          var pattern = template.split(/(\{\d+\})/).map(function(part) {
            var slot = /^\{(\d+)\}$/.exec(part);
            if (!slot) return part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            slots.push(Number(slot[1]));
            if (key === 'greetingWithName' || key === 'lastNgo_name') return '([\\s\\S]+)';
            if (key.indexOf('history_') === 0) return '((?: · [\\s\\S]+)?)';
            if (key === 'pointsProgressText' && slot[1] === '1') return '(Basic|Bronze|Silver|Gold|Platinum|Legend)';
            return '([-+]?(?:[0-9][0-9 \\u00a0\\u202f.,]*[0-9]|[0-9]))';
          }).join('');
          return { expression: new RegExp('^' + pattern + '$'), slots: slots };
        });
      });
    }
    for (var i = 0; i < keys.length; i += 1) {
      var key = keys[i], matchers = profileDynamicMatchers[key];
      for (var j = 0; j < matchers.length; j += 1) {
        var match = matchers[j].expression.exec(text);
        if (!match || match[0] !== text) continue;
        var params = [];
        matchers[j].slots.forEach(function(slot, index) { params[slot] = match[index + 1]; });
        return PROFILE_DYNAMIC_COPY[key][['hu', 'en', 'de', 'fr'].indexOf(language)].replace(/\{(\d+)\}/g, function(_, slot) {
          return params[Number(slot)]; // Callback preserves literal $&, $1 and $` in names.
        });
      }
    }
    return null;
  }

  // Versioned seed copy only: key AND the complete name/title pair must match.
  // Custom or incomplete metadata is never inferred from a badge key alone.
  var PROFILE_BADGE_COPY = {
    "streak_3": {"hu": ["3 napos streak", "Egymás után 3 nap aktivitás."], "en": ["3-day streak", "Activity for 3 days in a row."], "de": ["3-Tage-Serie", "An 3 aufeinanderfolgenden Tagen aktiv."], "fr": ["Série de 3 jours", "Activité pendant 3 jours consécutifs."]},
    "streak_7": {"hu": ["7 napos streak", "Egymás után 7 nap aktivitás."], "en": ["7-day streak", "Activity for 7 days in a row."], "de": ["7-Tage-Serie", "An 7 aufeinanderfolgenden Tagen aktiv."], "fr": ["Série de 7 jours", "Activité pendant 7 jours consécutifs."]},
    "streak_30": {"hu": ["30 napos streak", "Egymás után 30 nap aktivitás."], "en": ["30-day streak", "Activity for 30 days in a row."], "de": ["30-Tage-Serie", "An 30 aufeinanderfolgenden Tagen aktiv."], "fr": ["Série de 30 jours", "Activité pendant 30 jours consécutifs."]},
    "streak_100": {"hu": ["100 napos streak", "Egymás után 100 nap aktivitás."], "en": ["100-day streak", "Activity for 100 days in a row."], "de": ["100-Tage-Serie", "An 100 aufeinanderfolgenden Tagen aktiv."], "fr": ["Série de 100 jours", "Activité pendant 100 jours consécutifs."]},
    "streak_365": {"hu": ["365 napos streak", "Egymás után 365 nap aktivitás."], "en": ["365-day streak", "Activity for 365 days in a row."], "de": ["365-Tage-Serie", "An 365 aufeinanderfolgenden Tagen aktiv."], "fr": ["Série de 365 jours", "Activité pendant 365 jours consécutifs."]},
    "views_1": {"hu": ["Első videó", "1 megtekintett videó."], "en": ["First video", "1 video viewed."], "de": ["Erstes Video", "1 angesehenes Video."], "fr": ["Première vidéo", "1 vidéo visionnée."]},
    "views_10": {"hu": ["10 videó", "10 megtekintett videó."], "en": ["10 videos", "10 videos viewed."], "de": ["10 Videos", "10 angesehene Videos."], "fr": ["10 vidéos", "10 vidéos visionnées."]},
    "views_100": {"hu": ["100 videó", "100 megtekintett videó."], "en": ["100 videos", "100 videos viewed."], "de": ["100 Videos", "100 angesehene Videos."], "fr": ["100 vidéos", "100 vidéos visionnées."]},
    "views_1000": {"hu": ["1000 videó", "1000 megtekintett videó."], "en": ["1000 videos", "1000 videos viewed."], "de": ["1000 Videos", "1000 angesehene Videos."], "fr": ["1000 vidéos", "1000 vidéos visionnées."]},
    "views_5000": {"hu": ["5000 videó", "5000 megtekintett videó."], "en": ["5000 videos", "5000 videos viewed."], "de": ["5000 Videos", "5000 angesehene Videos."], "fr": ["5000 vidéos", "5000 vidéos visionnées."]},
    "views_10000": {"hu": ["10000 videó", "10000 megtekintett videó."], "en": ["10000 videos", "10000 videos viewed."], "de": ["10000 Videos", "10000 angesehene Videos."], "fr": ["10000 vidéos", "10000 vidéos visionnées."]},
    "first_vote": {"hu": ["Első szavazat", "Első szavazat leadása."], "en": ["First vote", "First vote cast."], "de": ["Erste Stimme", "Erste abgegebene Stimme."], "fr": ["Premier vote", "Premier vote exprimé."]},
    "votes_10": {"hu": ["10 szavazat", "10 leadott szavazat."], "en": ["10 votes", "10 votes cast."], "de": ["10 Stimmen", "10 abgegebene Stimmen."], "fr": ["10 votes", "10 votes exprimés."]},
    "votes_100": {"hu": ["100 szavazat", "100 leadott szavazat."], "en": ["100 votes", "100 votes cast."], "de": ["100 Stimmen", "100 abgegebene Stimmen."], "fr": ["100 votes", "100 votes exprimés."]},
    "votes_1000": {"hu": ["1000 szavazat", "1000 leadott szavazat."], "en": ["1000 votes", "1000 votes cast."], "de": ["1000 Stimmen", "1000 abgegebene Stimmen."], "fr": ["1000 votes", "1000 votes exprimés."]},
    "votes_5000": {"hu": ["5000 szavazat", "5000 leadott szavazat."], "en": ["5000 votes", "5000 votes cast."], "de": ["5000 Stimmen", "5000 abgegebene Stimmen."], "fr": ["5000 votes", "5000 votes exprimés."]},
    "votes_10000": {"hu": ["10000 szavazat", "10000 leadott szavazat."], "en": ["10000 votes", "10000 votes cast."], "de": ["10000 Stimmen", "10000 abgegebene Stimmen."], "fr": ["10000 votes", "10000 votes exprimés."]},
    "ngo_1": {"hu": ["Első szervezet", "Első támogatott szervezet."], "en": ["First organization", "First supported organization."], "de": ["Erste Organisation", "Erste unterstützte Organisation."], "fr": ["Première organisation", "Première organisation soutenue."]},
    "ngo_10": {"hu": ["10 szervezet", "10 különböző szervezet támogatása."], "en": ["10 organizations", "Support of 10 different organizations."], "de": ["10 Organisationen", "Unterstützung von 10 verschiedenen Organisationen."], "fr": ["10 organisations", "Soutien de 10 organisations différentes."]},
    "ngo_100": {"hu": ["100 szervezet", "100 különböző szervezet támogatása."], "en": ["100 organizations", "Support of 100 different organizations."], "de": ["100 Organisationen", "Unterstützung von 100 verschiedenen Organisationen."], "fr": ["100 organisations", "Soutien de 100 organisations différentes."]},
    "ngo_loyal": {"hu": ["Hűséges támogató", "Ugyanazon szervezet támogatása 30 napon át."], "en": ["Loyal supporter", "Support of the same organization for 30 days."], "de": ["Treuer Unterstützer", "Unterstützung derselben Organisation 30 Tage lang."], "fr": ["Soutien fidèle", "Soutien de la même organisation pendant 30 jours."]},
    "first_edu_video": {"hu": ["Első edukáció", "Első edukációs videó."], "en": ["First educational video", "First educational video."], "de": ["Erste Weiterbildung", "Erstes Bildungsvideo."], "fr": ["Première vidéo éducative", "Première vidéo éducative."]},
    "edu_complete_5": {"hu": ["5 edukáció", "5 edukációs videó."], "en": ["5 educational videos", "5 educational videos."], "de": ["5 Weiterbildungen", "5 Bildungsvideos."], "fr": ["5 vidéos éducatives", "5 vidéos éducatives."]},
    "edu_complete_20": {"hu": ["20 edukáció", "20 edukációs videó."], "en": ["20 educational videos", "20 educational videos."], "de": ["20 Weiterbildungen", "20 Bildungsvideos."], "fr": ["20 vidéos éducatives", "20 vidéos éducatives."]},
    "edu_complete_50": {"hu": ["50 edukáció", "50 edukációs videó."], "en": ["50 educational videos", "50 educational videos."], "de": ["50 Weiterbildungen", "50 Bildungsvideos."], "fr": ["50 vidéos éducatives", "50 vidéos éducatives."]},
    "edu_complete_100": {"hu": ["100 edukáció", "100 edukációs videó."], "en": ["100 educational videos", "100 educational videos."], "de": ["100 Weiterbildungen", "100 Bildungsvideos."], "fr": ["100 vidéos éducatives", "100 vidéos éducatives."]},
    "quiz_master": {"hu": ["Quiz mester", "10 kvíz sikeres teljesítése."], "en": ["Quiz master", "Successful completion of 10 quizzes."], "de": ["Quiz-Meister", "10 Quiz erfolgreich abgeschlossen."], "fr": ["Maître du quiz", "Réussite de 10 quiz."]},
    "first_offer": {"hu": ["Első offer", "Első offerwall teljesítés."], "en": ["First offer", "First offerwall completion."], "de": ["Erstes Angebot", "Erste Offerwall-Absolvierung."], "fr": ["Première offre", "Première réalisation offerwall."]},
    "offers_10": {"hu": ["10 offer", "10 offerwall teljesítés."], "en": ["10 offers", "10 offerwall completions."], "de": ["10 Angebote", "10 Offerwall-Absolvierungen."], "fr": ["10 offres", "10 réalisations offerwall."]},
    "offers_100": {"hu": ["100 offer", "100 offerwall teljesítés."], "en": ["100 offers", "100 offerwall completions."], "de": ["100 Angebote", "100 Offerwall-Absolvierungen."], "fr": ["100 offres", "100 réalisations offerwall."]},
    "offers_500": {"hu": ["500 offer", "500 offerwall teljesítés."], "en": ["500 offers", "500 offerwall completions."], "de": ["500 Angebote", "500 Offerwall-Absolvierungen."], "fr": ["500 offres", "500 réalisations offerwall."]},
    "offers_1000": {"hu": ["1000 offer", "1000 offerwall teljesítés."], "en": ["1000 offers", "1000 offerwall completions."], "de": ["1000 Angebote", "1000 Offerwall-Absolvierungen."], "fr": ["1000 offres", "1000 réalisations offerwall."]},
    "high_value_offer": {"hu": ["Prémium offer", "Egy kiemelkedő értékű teljesítés."], "en": ["Premium offer", "One outstanding-value completion."], "de": ["Premium-Angebot", "Eine Absolvierung mit herausragendem Wert."], "fr": ["Offre premium", "Une réalisation de valeur exceptionnelle."]},
    "early_adopter": {"hu": ["Korai belépő", "Korai használó."], "en": ["Early adopter", "Early user."], "de": ["Früher Nutzer", "Früher Nutzer."], "fr": ["Membre pionnier", "Utilisateur de la première heure."]},
    "seasonal_xmas": {"hu": ["Téli kihívás", "Különleges téli kampány jelvény."], "en": ["Winter challenge", "Special winter campaign badge."], "de": ["Winter-Challenge", "Besonderes Winterkampagnen-Abzeichen."], "fr": ["Défi hivernal", "Badge spécial de campagne hivernale."]},
    "referral_1": {"hu": ["Ajánlás", "Első sikeres ajánlás."], "en": ["Referral", "First successful referral."], "de": ["Empfehlung", "Erste erfolgreiche Empfehlung."], "fr": ["Parrainage", "Premier parrainage réussi."]},
    "anniversary_1": {"hu": ["Évforduló", "1 éve Sharity tag."], "en": ["Anniversary", "Sharity member for 1 year."], "de": ["Jahrestag", "Seit 1 Jahr Sharity-Mitglied."], "fr": ["Anniversaire", "Membre Sharity depuis 1 an."]},
    "anniversary_2": {"hu": ["Évforduló", "2 éve Sharity tag."], "en": ["Anniversary", "Sharity member for 2 years."], "de": ["Jahrestag", "Seit 2 Jahren Sharity-Mitglied."], "fr": ["Anniversaire", "Membre Sharity depuis 2 ans."]},
    "anniversary_3": {"hu": ["Évforduló", "3 éve Sharity tag."], "en": ["Anniversary", "Sharity member for 3 years."], "de": ["Jahrestag", "Seit 3 Jahren Sharity-Mitglied."], "fr": ["Anniversaire", "Membre Sharity depuis 3 ans."]},
    "anniversary_4": {"hu": ["Évforduló", "4 éve Sharity tag."], "en": ["Anniversary", "Sharity member for 4 years."], "de": ["Jahrestag", "Seit 4 Jahren Sharity-Mitglied."], "fr": ["Anniversaire", "Membre Sharity depuis 4 ans."]},
    "anniversary_5": {"hu": ["Évforduló", "5 éve Sharity tag."], "en": ["Anniversary", "Sharity member for 5 years."], "de": ["Jahrestag", "Seit 5 Jahren Sharity-Mitglied."], "fr": ["Anniversaire", "Membre Sharity depuis 5 ans."]}
  };

  function applyProfileBadgeCopy() {
    var language = profileCopyLanguage();
    if (!language) return;
    document.querySelectorAll('.impactshop-profile-shell [data-role="legacy-badges"] > .impactshop-legacy-badge[data-badge-key]').forEach(function(badge) {
      var key = badge.getAttribute('data-badge-key');
      var copy = Object.prototype.hasOwnProperty.call(PROFILE_BADGE_COPY, key) ? PROFILE_BADGE_COPY[key] : null;
      var name = badge.children.length === 2 ? badge.children[1] : null;
      var plainName = name && name.tagName === 'SPAN' && name.childNodes.length === 1 && name.firstChild.nodeType === 3;
      var known = plainName && copy && Object.keys(copy).some(function(lang) {
        return name.textContent === copy[lang][0] && badge.title === copy[lang][1];
      });
      if (!known) {
        if (profileDynamicLangOrigins.has(badge)) {
          var original = profileDynamicLangOrigins.get(badge);
          if (original === null) badge.removeAttribute('lang'); else badge.setAttribute('lang', original);
          profileDynamicLangOrigins.delete(badge);
        }
        return;
      }
      if (!profileDynamicLangOrigins.has(badge)) profileDynamicLangOrigins.set(badge, badge.getAttribute('lang'));
      if (name.firstChild.nodeValue !== copy[language][0]) name.firstChild.nodeValue = copy[language][0];
      if (badge.title !== copy[language][1]) badge.title = copy[language][1];
      if (badge.lang !== language) badge.lang = language;
    });
  }

  function applyProfileDynamicCopy() {
    var language = profileCopyLanguage();
    document.querySelectorAll('.impactshop-profile-shell').forEach(function(root) {
      // Without the new selector, keep legacy English behavior. Own only the
      // greeting and NGO there so names never run through global replacements.
      var legacyGreeting = !language && isEnglishMode(root);
      if (!language && !legacyGreeting) return;
      Object.keys(PROFILE_DYNAMIC_ROLES).forEach(function(role) {
        if (!language && role !== 'greeting' && role !== 'last-ngo') return;
        root.querySelectorAll('[data-role="' + role + '"]').forEach(function(el) {
          var targets = role === 'points-benefits' ? el.querySelectorAll(':scope > span') : [el];
          if (role === 'account-message') targets = el.querySelectorAll(':scope > button');
          if (role === 'last-ngo') targets = [el].concat(Array.prototype.slice.call(el.querySelectorAll(':scope > span, :scope > button')));
          if (role === 'points-history') {
            targets = [];
            el.querySelectorAll(':scope > li').forEach(function(row) {
              if (row.children.length !== 2) return;
              var left = row.children[0], right = row.children[1];
              if (left.tagName !== 'SPAN' || right.tagName !== 'SPAN') return;
              if (left.childNodes.length !== 1 || left.firstChild.nodeType !== 3 || right.childNodes.length !== 1 || right.firstChild.nodeType !== 3) return;
              targets.push(left, right);
            });
          }
          Array.prototype.forEach.call(targets, function(target) {
            if (!target.hasAttribute('data-sharity-dynamic-copy')) target.setAttribute('data-sharity-dynamic-copy', '');
            var known = false;
            Array.prototype.forEach.call(target.childNodes, function(node) {
              if (node.nodeType !== 3) return;
              var keys = role === 'points-history' && target === target.parentNode.lastElementChild ? ['pointsTotal'] : PROFILE_DYNAMIC_ROLES[role];
              var text = profileDynamicText(node.nodeValue, keys, language || 'en');
              if (text === null) return;
              known = true;
              if (node.nodeValue !== text) node.nodeValue = text;
            });
            if (known) {
              if (!profileDynamicLangOrigins.has(target)) profileDynamicLangOrigins.set(target, target.getAttribute('lang'));
              if (target.lang !== (language || 'en')) target.lang = language || 'en';
            } else if (profileDynamicLangOrigins.has(target)) {
              var original = profileDynamicLangOrigins.get(target);
              if (original === null) target.removeAttribute('lang'); else target.setAttribute('lang', original);
              profileDynamicLangOrigins.delete(target);
            }
          });
        });
      });
    });
  }

  function isManagedUi(el) {
    return el && el.closest && el.closest(
      '[data-sharity-preferences-managed], [data-sharity-profile-copy], [data-sharity-dynamic-copy], ' +
      '.impactshop-profile-shell [data-role="nickname-display"], ' +
      '.impactshop-profile-shell [data-role="pseudo-display"], .impactshop-profile-shell [data-role="recovery-display"], ' +
      '.impactshop-profile-shell [data-role="referral-code"], .impactshop-profile-shell [data-role="referral-link"], ' +
      '.impactshop-profile-shell [data-role="legacy-badges"], ' +
      '.impactshop-profile-shell [data-role="account-message"], .impactshop-profile-shell [data-role="broadcast-message"]'
    );
  }

  function normalizeText(text) {
    return String(text || '').replace(/\s+/g, ' ').trim();
  }

  function isShopPage() {
    return /^\/impactshop(\/|$)/.test(window.location.pathname || '/');
  }

  function currentLocale() {
    try {
      var url = new URL(window.location.href);
      return {
        lang: String(url.searchParams.get('lang') || '').toLowerCase(),
        country: String(url.searchParams.get('country') || '').toLowerCase()
      };
    } catch (e) {
      return { lang: '', country: '' };
    }
  }

  function shouldKeepLocaleContext() {
    var locale = currentLocale();
    return locale.lang === 'en' || locale.country === 'us';
  }

  function isLocaleAwarePath(pathname) {
    for (var i = 0; i < LOCALE_PATH_PATTERNS.length; i += 1) {
      if (LOCALE_PATH_PATTERNS[i].test(String(pathname || ''))) {
        return true;
      }
    }
    return false;
  }

  function decorateLocaleAwareLink(anchor) {
    if (!anchor || typeof anchor.getAttribute !== 'function' || isManagedUi(anchor)) return;
    var href = String(anchor.getAttribute('href') || '').trim();
    if (!href || href.charAt(0) === '#') return;
    if (/^(mailto:|tel:|javascript:)/i.test(href)) return;

    var locale = currentLocale();
    if (locale.lang !== 'en' && locale.country !== 'us') return;

    try {
      var url = new URL(href, window.location.href);
      if (url.origin !== window.location.origin) return;
      if (!isLocaleAwarePath(url.pathname)) return;

      if (locale.lang === 'en') {
        url.searchParams.set('lang', 'en');
      }
      if (locale.country === 'us') {
        url.searchParams.set('country', 'us');
      }

      var next = url.toString();
      if (anchor.href !== next) {
        anchor.href = next;
      }
    } catch (e) {}
  }

  function translateExactMatches(container, selector, map) {
    if (!container) return;
    container.querySelectorAll(selector).forEach(function(el) {
      if (isManagedUi(el)) return;
      var key = normalizeText(el.textContent);
      if (!key || !Object.prototype.hasOwnProperty.call(map, key)) return;
      el.textContent = map[key];
    });
  }

  function translateExactRenderedMatches(container, selector, map) {
    if (!container) return;
    container.querySelectorAll(selector).forEach(function(el) {
      if (isManagedUi(el)) return;
      var key = normalizeText(typeof el.innerText === 'string' ? el.innerText : el.textContent);
      if (!key || !Object.prototype.hasOwnProperty.call(map, key)) return;
      el.textContent = map[key];
    });
  }

  function translateExactRenderedHtml(container, selector, map) {
    if (!container) return;
    container.querySelectorAll(selector).forEach(function(el) {
      if (isManagedUi(el)) return;
      var key = normalizeText(typeof el.innerText === 'string' ? el.innerText : el.textContent);
      if (!key || !Object.prototype.hasOwnProperty.call(map, key)) return;
      el.innerHTML = map[key];
    });
  }

  function translateExactAttributeMatches(container, selector, attr, map) {
    if (!container) return;
    container.querySelectorAll(selector).forEach(function(el) {
      if (isManagedUi(el)) return;
      var value = normalizeText(el.getAttribute(attr));
      if (!value || !Object.prototype.hasOwnProperty.call(map, value)) return;
      el.setAttribute(attr, map[value]);
    });
  }

  function applyShopSpecificTranslations(container) {
    if (!container || !isShopPage()) return;

    container.querySelectorAll('.ifx-pill[data-cat]').forEach(function(el) {
      if (isManagedUi(el)) return;
      var key = String(el.getAttribute('data-cat') || '').trim();
      if (key === '__ALL__') key = 'Összes';
      if (!key) key = normalizeText(el.textContent);
      if (key && Object.prototype.hasOwnProperty.call(CATEGORY_TRANSLATIONS, key)) {
        el.textContent = CATEGORY_TRANSLATIONS[key];
      }
    });

    translateExactMatches(container, '.ifx-pill', CATEGORY_TRANSLATIONS);
    translateExactMatches(container, '.ifx-badge', EXACT_TRANSLATIONS);
    translateExactMatches(container, '.badge', EXACT_TRANSLATIONS);
    translateExactMatches(container, '.ticker-badge span, .elementor-heading-title, .elementor-icon-box-title, .elementor-icon-box-title span, .elementor-icon-list-items, .elementor-button-text', EXACT_TRANSLATIONS);
    translateExactRenderedMatches(container, '.elementor-heading-title, .elementor-icon-box-title, .elementor-icon-box-title span', EXACT_TRANSLATIONS);
    translateExactRenderedMatches(container, '.impactshop-identity-info-bubble li, .elementor-widget-text-editor, .elementor-widget-text-editor p, .icn-primary, .icn-title, .label, .price', EXACT_RENDERED_TRANSLATIONS);
    translateExactRenderedHtml(container, '.impactshop-identity-info-bubble li', {
      'Ha meg akarod tartani, kattints a Save gombra, hogy a jelszavaid közé kerüljön.': 'If you want to keep it, click <strong>Save</strong> so it is added to your saved passwords.',
      'Ha máshova mentenéd, használd a Copy gombot.': 'If you want to save it elsewhere, use <strong>Copy</strong>.',
      'Ha később visszajössz és a fiókod nem jelenik meg, kattints a Restore gombra.': 'If you come back later and your account does not appear, click <strong>Restore</strong>.'
    });
    translateExactMatches(container, '.sharity-action-bar a span:last-child', EXACT_TRANSLATIONS);
    translateExactMatches(container, '.sharity-slc__header, .sharity-slc__section-title', EXACT_TRANSLATIONS);
    translateExactAttributeMatches(container, '.sharity-action-bar, #sharity-slc-btn, #sharity-slc-sheet, .sharity-slc__row-check', 'aria-label', EXACT_TRANSLATIONS);

    container.querySelectorAll('a[href]').forEach(decorateLocaleAwareLink);
  }

  function isEnglishMode(root) {
    try {
      var url = new URL(window.location.href);
      if (String(url.searchParams.get('lang') || '').toLowerCase() === 'en') {
        return true;
      }
    } catch (e) {}

    if (!root) return false;
    // A translated static heading must not switch the rest of a HU page to EN.
    if (root.classList.contains('impactshop-profile-shell') && profileCopyLanguage()) return false;

    return Array.prototype.some.call(root.querySelectorAll('*'), function(el) {
      var text = String(el.textContent || '').replace(/\s+/g, ' ').trim();
      return text.indexOf('Your profile') === 0 || text.indexOf('Hi, welcome to Sharity.') === 0;
    });
  }

  function translateTextNodes(container) {
    if (!container) return;
    var walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, {
      acceptNode: function(node) {
        if (!node || !node.parentNode) return NodeFilter.FILTER_REJECT;
        var parent = node.parentNode;
        if (isManagedUi(parent)) return NodeFilter.FILTER_REJECT;
        if (/^(SCRIPT|STYLE|NOSCRIPT|TEXTAREA)$/i.test(parent.nodeName)) {
          return NodeFilter.FILTER_REJECT;
        }
        if (!String(node.textContent || '').trim()) {
          return NodeFilter.FILTER_REJECT;
        }
        return NodeFilter.FILTER_ACCEPT;
      }
    });

    var current;
    while ((current = walker.nextNode())) {
      var translated = translateDynamicText(String(current.textContent || ''));
      if (translated && translated !== current.textContent) {
        current.textContent = translated;
      }
    }
  }

  function translateAttributes(container) {
    if (!container) return;
    container.querySelectorAll('[placeholder], [title], [aria-label], input[type="submit"], input[type="button"]').forEach(function(el) {
      if (isManagedUi(el)) return;
      ['placeholder', 'title', 'aria-label', 'value'].forEach(function(attr) {
        if (el.closest('.impactshop-profile-shell') &&
            (attr === 'value' || (attr === 'placeholder' && profileCopyLanguage()))) return;
        var value = el.getAttribute(attr);
        if (!value) return;
        var translated = translateDynamicText(String(value));
        if (translated && translated !== value) {
          el.setAttribute(attr, translated);
        }
      });
    });
  }

  function setTranslatedTextAll(container, selector) {
    if (!container) return;
    container.querySelectorAll(selector).forEach(function(el) {
      if (isManagedUi(el)) return;
      var translated = translateDynamicText(String(el.textContent || ''));
      if (translated && translated !== el.textContent) {
        el.textContent = translated;
      }
    });
  }

  function applyToContainer(container) {
    if (!container || isManagedUi(container)) return;
    translateTextNodes(container);
    translateAttributes(container);
    applyShopSpecificTranslations(container);
    setTranslatedTextAll(container, '[data-role="greeting"]');
    setTranslatedTextAll(container, '.impactshop-identity-restore .impactshop-identity-hint');
    setTranslatedTextAll(container, '[data-role="points-history"] li span');
  }

  var SHOP_PAGE_COPY = {
    "556a678": {
      "Kedvezményes ajánlatok": [
        "Special offers",
        "Sonderangebote",
        "Offres spéciales"
      ]
    },
    "11a641f": {
      "Kuponok %": [
        "Coupons %",
        "Coupons %",
        "Coupons %"
      ]
    },
    "9189c1c": {
      "Megmutatod?": [
        "Will you share it?",
        "Teilst du es?",
        "Et si vous partagiez ?"
      ]
    },
    "cff1f7c": {
      "Hogyan működik?": [
        "How does it work?",
        "Wie funktioniert es?",
        "Comment ça marche ?"
      ]
    },
    "0df1a86": {
      "Válassz boltot": [
        "Pick a shop",
        "Wähle einen Shop",
        "Choisissez une boutique"
      ],
      "Válaszd ki, hol szeretnél vásárolni ! Kattints a webshop logóra, ajánlatra, kuponra. Adományszerzéshez innen kell indítanod a vásárlást.": [
        "Choose where you would like to shop! Click a webshop logo, offer or coupon. To generate support, you must start your purchase from here.",
        "Wähle, wo du einkaufen möchtest! Klicke auf ein Webshop-Logo, ein Angebot oder einen Coupon. Um Unterstützung zu erzeugen, musst du deinen Einkauf von hier aus starten.",
        "Choisissez où vous souhaitez acheter ! Cliquez sur un logo de boutique, une offre ou un coupon. Pour générer du soutien, vous devez démarrer votre achat ici."
      ]
    },
    "332e6a1": {
      "Válassz ügyet": [
        "Choose a cause",
        "Wähle ein Anliegen",
        "Choisissez une cause"
      ],
      "Ha nem egy szervezettől érkeztél ide, akkor a következő lépés a szervezet kiválasztása a listából.": [
        "If you did not arrive here from an organisation, the next step is to choose the organisation from the list.",
        "Wenn du nicht über eine Organisation hierher gekommen bist, ist der nächste Schritt, die Organisation aus der Liste auszuwählen.",
        "Si vous n’êtes pas arrivé ici via une organisation, l’étape suivante consiste à choisir l’organisation dans la liste."
      ]
    },
    "9119697": {
      "Vásárolj és támogass": [
        "Shop and support",
        "Einkaufen und unterstützen",
        "Achetez et soutenez"
      ],
      "Nincs más dolgod, mint a hagyományos módon vásárolni a webshopban. Adományod meg fog jelenni a \"Kik támogattak mostanában ?\" listában, amit meg is oszthatsz.": [
        "Nothing else to do but shop in the webshop as usual. Your donation will show up in the \"Who supported recently?\" list, which you can also share.",
        "Du musst nichts weiter tun, als wie gewohnt im Webshop einzukaufen. Deine Spende erscheint in der Liste „Wer hat zuletzt unterstützt?“, die du auch teilen kannst.",
        "Il n’y a rien d’autre à faire que d’acheter normalement dans la boutique. Votre don apparaîtra dans la liste « Qui a soutenu récemment ? », que vous pouvez aussi partager."
      ]
    },
    "62ae0c2": {
      "Válassz egy webshopot, egy akciós ajánlatot, vagy használd a kedvezménykuponok egyikét – nincs más dolgot,": [
        "Choose a webshop, a special offer or a discount coupon – simply",
        "Wähle einen Webshop, ein Sonderangebot oder einen Rabattcoupon –",
        "Choisissez une boutique en ligne, une offre spéciale ou un coupon de réduction :"
      ],
      "kattints rá": [
        "click it",
        "klicke darauf",
        "cliquez dessus"
      ],
      "ezen az oldalon": [
        "on this page",
        "auf dieser Seite",
        "sur cette page"
      ],
      "és már vásárolhatsz is,": [
        "and start shopping,",
        "und kaufe wie gewohnt ein,",
        "et commencez vos achats,"
      ],
      "miközben adományt is gyűjtesz. Csak az": [
        "while generating support. Only",
        "während du Unterstützung sammelst. Nur",
        "tout en générant du soutien. Seuls les"
      ],
      "innen indított vásárlás számít": [
        "purchases started here count",
        "ein hier gestarteter Einkauf zählt",
        "achats démarrés ici sont pris en compte"
      ]
    },
    "66164a1": {
      "Egy kattintással megoszthatod a kedvenc platformjaidon, így": [
        "With one click you can share it on your favourite platforms and",
        "Mit einem Klick kannst du es auf deinen Lieblingsplattformen teilen und",
        "En un clic, partagez sur vos plateformes préférées pour"
      ],
      "segíthetsz eljuttatni egy jó ügyet,": [
        "help bring a good cause",
        "ein gutes Anliegen",
        "faire connaître une bonne cause"
      ],
      "minél több emberhez.": [
        "to more people.",
        "mehr Menschen näherbringen.",
        "à plus de personnes."
      ]
    }
  };
  var SHOP_PAGE_ESTIMATE = {
    "hu": "A támogatási összegek becslések; a végleges összeget jóváhagyás és elszámolás után állapítjuk meg.",
    "en": "Support amounts are estimates; the final amount is determined after approval and settlement.",
    "de": "Die Unterstützungsbeträge sind Schätzungen; der endgültige Betrag steht nach Genehmigung und Abrechnung fest.",
    "fr": "Les montants de soutien sont estimatifs ; le montant définitif est établi après validation et règlement."
  };
  var shopPageCopyOrigins = new WeakMap();
  function applyShopPageCopy() {
    if (!isShopPage()) return;
    var language = profileCopyLanguage(), page = document.querySelector('.elementor-16348');
    if (!language || !page) return;
    Object.keys(SHOP_PAGE_COPY).forEach(function(id) {
      page.querySelectorAll('.elementor-element-' + id + '[data-id="' + id + '"]').forEach(function(widget) {
        if (isManagedUi(widget) && !widget.hasAttribute('data-sharity-shop-copy')) return;
        var table = SHOP_PAGE_COPY[id], matched = false;
        widget.querySelectorAll('.elementor-widget-container > p:not([data-sharity-shop-estimate]), .elementor-heading-title, .elementor-icon-box-title, .elementor-icon-box-description').forEach(function(target) {
          var walker = document.createTreeWalker(target, NodeFilter.SHOW_TEXT, {
            acceptNode: function(node) { return node.parentElement.closest('script,style,noscript,textarea,[contenteditable]') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT; }
          });
          var node;
          while ((node = walker.nextNode())) {
            var state = shopPageCopyOrigins.get(node), current = node.nodeValue;
            // An editor/provider change is data; don't restore stale saved copy over it.
            if (state && state.last !== current) { shopPageCopyOrigins.delete(node); state = null; }
            if (!state) {
              var key = normalizeText(current);
              Object.keys(table).some(function(source) {
                if ([source, translateDynamicText(source)].concat(table[source]).map(normalizeText).indexOf(key) === -1) return false;
                var edge = current.match(/^(\s*)[\s\S]*?(\s*)$/);
                state = { source: source, prefix: edge[1], suffix: edge[2], original: key === source ? current : edge[1] + source + edge[2], last: current };
                shopPageCopyOrigins.set(node, state); return true;
              });
            }
            if (!state) continue;
            matched = true;
            var next = language === 'hu' ? state.original : state.prefix + table[state.source][['en','de','fr'].indexOf(language)] + state.suffix;
            if (node.nodeValue !== next) node.nodeValue = next;
            state.last = next;
          }
        });
        if (!matched) return;
        widget.setAttribute('data-sharity-shop-copy', '');
        widget.setAttribute('data-sharity-preferences-managed', '');
        if (widget.lang !== language) widget.lang = language;
        if (id === '9119697') {
          var note = widget.querySelector('[data-sharity-shop-estimate]');
          if (!note) { note = document.createElement('p'); note.setAttribute('data-sharity-shop-estimate',''); (widget.querySelector('.elementor-icon-box-content') || widget).appendChild(note); }
          if (note.textContent !== SHOP_PAGE_ESTIMATE[language]) note.textContent = SHOP_PAGE_ESTIMATE[language];
        }
      });
    });
  }


  function apply() {
    applyProfileCopy();
    applyProfileDynamicCopy();
    applyProfileBadgeCopy();
    applyShopPageCopy();
    var roots = Array.prototype.slice.call(document.querySelectorAll('.impactshop-identity-panel'));
    if (!roots.length) {
      return;
    }

    var english = false;
    roots.forEach(function(root) {
      if (isEnglishMode(root)) {
        english = true;
      }
    });
    if (!english) {
      return;
    }

    document.documentElement.lang = 'en';
    document.title = translateDynamicText(document.title);

    var containers = [];
    roots.forEach(function(root) { containers.push(root); });
    [
      document.querySelector('header.elementor-location-header'),
      document.querySelector('main'),
      document.querySelector('#impactshop'),
      document.querySelector('.elementor.elementor-16348'),
      document.body,
      document.querySelector('footer'),
      document.querySelector('.sharity-message-popover'),
      document.querySelector('#sharity-slc-btn'),
      document.querySelector('#sharity-slc-sheet'),
      document.querySelector('.bottom-nav'),
      document.querySelector('.bottom-nav-bar'),
      document.querySelector('.mobile-bottom-nav'),
      document.querySelector('.mobile-nav'),
      document.querySelector('.mobile-nav-bar'),
      document.querySelector('.impactshop-action-bar'),
      document.querySelector('.sharity-action-bar')
    ].forEach(function(node) {
      if (node) containers.push(node);
    });

    containers.forEach(applyToContainer);

    if (shouldKeepLocaleContext()) {
      decorateLocaleAwareLink(document.querySelector('a[href]'));
      document.querySelectorAll('a[href]').forEach(decorateLocaleAwareLink);
    }
  }

  ready(function() {
    apply();
    var scheduled = false;
    var observer = new MutationObserver(function() {
      if (scheduled) return;
      scheduled = true;
      requestAnimationFrame(function() {
        scheduled = false;
        apply();
      });
    });
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      characterData: true,
      attributes: true,
      attributeFilter: ['title', 'aria-label', 'placeholder', 'value', 'lang']
    });

    if (isShopPage()) {
      var reapplyRuns = 0;
      var reapplyTimer = window.setInterval(function() {
        reapplyRuns += 1;
        apply();
        if (reapplyRuns >= 20) {
          window.clearInterval(reapplyTimer);
        }
      }, 1000);

      document.addEventListener('visibilitychange', function() {
        if (!document.hidden) apply();
      });
      window.addEventListener('pageshow', apply);
    }
  });
})();
