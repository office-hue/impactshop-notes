function getBannersAsObjects() {
  const sh = SpreadsheetApp.getActive().getSheetByName('Banners');
  const vals = sh.getDataRange().getValues(); // [ [slug,img,href,label,category], ... ]
  const rows = vals.slice(1);

  return rows.map(r => {
    const [slug, img, href, label, category] = r;
    let data;
    try { data = JSON.parse(label); }
    catch { data = { title: String(label||''), price: '', old_price: '', discount_pct: 0, price_num: null, old_price_num: null }; }

    // egységesített objektum a frontendnek:
    return {
      slug,
      img,
      href,
      category,
      title: data.title || 'Ajánlat',
      price: data.price || '',
      old_price: data.old_price || '',
      discount_pct: data.discount_pct || 0,
      price_num: data.price_num ?? null,
      old_price_num: data.old_price_num ?? null
    };
  });
}

// Egyszerű JSON végpont
function doGet() {
  const data = getBannersAsObjects();
  return ContentService
    .createTextOutput(JSON.stringify({ items: data }))
    .setMimeType(ContentService.MimeType.JSON);
}