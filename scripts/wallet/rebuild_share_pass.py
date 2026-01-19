import json
import os
import datetime
from pathlib import Path


def anchor(url: str, text: str) -> str:
    """Slugos CTA linkhez HTML anchor."""
    return f'<a href="{url.replace("&", "&#038;")}">{text}</a>'

def load_json(path: Path) -> dict:
    with path.open() as handle:
        return json.load(handle)

def save_json(path: Path, payload: dict) -> None:
    with path.open('w') as handle:
        json.dump(payload, handle, ensure_ascii=False, separators=(',', ':'))

def main() -> None:
    api_json = os.environ.get('API_JSON')
    slug = os.environ.get('SLUG')
    pass_path = Path(os.environ['PASS_PATH'])
    system_message = (os.environ.get('SYSTEM_MESSAGE') or '').strip()

    if not api_json or not slug:
        raise SystemExit('Missing API_JSON or SLUG env variable')

    data = json.loads(api_json)
    payload = load_json(pass_path)

    announcement_raw = data.get('announcement')
    announcement_obj = announcement_raw if isinstance(announcement_raw, dict) else {}
    announcement_text = (announcement_obj.get('text') or '').strip()
    announcement_url = (announcement_obj.get('url') or '').strip()
    org_name = data.get('name') or 'Impact Shop'
    amount = (data.get('amount') or {}).get('formatted', '').strip()
    rank = data.get('rank')
    badge_label = ((data.get('badge_status') or {}).get('label') or 'Aktív').upper()
    share_url = data.get('share_url') or f'https://app.sharity.hu/ngo/{slug}/share/'
    tombola_url = (data.get('tombola_url') or '').strip()
    video_url = (data.get('video_support_url') or '').strip()
    cta_url = f'https://app.sharity.hu/impactshop/?d1={slug}&ngo={slug}&src=wallet-pass'
    serial = datetime.datetime.utcnow().strftime('wallet-share-%Y%m%dT%H%M%S')

    payload['organizationName'] = org_name
    payload['serialNumber'] = serial
    payload['appLaunchURL'] = cta_url
    payload['barcode'] = {
        'message': cta_url,
        'format': 'PKBarcodeFormatQR',
        'messageEncoding': 'iso-8859-1'
    }
    payload['barcodes'] = [payload['barcode']]

    store = payload.setdefault('storeCard', {})
    primary = store.setdefault('primaryFields', [{}])
    if primary:
        primary[0]['key'] = 'amount'
        primary[0]['label'] = 'Összegyűjtve'
        primary[0]['value'] = amount or primary[0].get('value', '')

    secondary = store.setdefault('secondaryFields', [])
    while len(secondary) < 2:
        secondary.append({})
    secondary[0].update({'key': 'badge', 'label': 'Státusz', 'value': badge_label})
    secondary[1].update({'key': 'rank', 'label': 'Aktuális helyezés', 'value': f'#{rank}' if rank else secondary[1].get('value', '#?')})

    aux = store.setdefault('auxiliaryFields', [])
    if aux:
        aux[0].update({'key': 'second_cta', 'label': 'Név', 'value': org_name})
    else:
        aux.append({'key': 'second_cta', 'label': 'Név', 'value': org_name})

    back_fields = [
        {
            'key': 'cta',
            'label': 'Impact Shop link',
            'value': cta_url,
            'dataDetectorTypes': ['PKDataDetectorTypeLink'],
            'attributedValue': anchor(cta_url, 'Impact Shop megnyitása'),
        }
    ]
    if tombola_url:
        back_fields.append({
            'key': 'tombola',
            'label': 'Tombola',
            'value': tombola_url,
            'dataDetectorTypes': ['PKDataDetectorTypeLink'],
            'attributedValue': anchor(tombola_url, 'Tombola megnyitása'),
        })
    if video_url:
        back_fields.append({
            'key': 'video',
            'label': 'Videós támogatás',
            'value': video_url,
            'dataDetectorTypes': ['PKDataDetectorTypeLink'],
            'attributedValue': anchor(video_url, 'Videós támogatás megnyitása'),
        })
    sharity_news_value = announcement_text or 'ImpactShop frissítés alatt'
    back_fields.append({
        'key': 'sharity_news',
        'label': 'Sharity hírek',
        'value': sharity_news_value,
    })
    if not system_message and announcement_url:
        system_message = f"{announcement_text}\n{announcement_url}".strip()
    if system_message and system_message != sharity_news_value:
        back_fields.append({
            'key': 'announcement',
            'label': 'Rendszerüzenet',
            'value': system_message,
        })
    store['backFields'] = back_fields

    user_info = payload.setdefault('userInfo', {})
    user_info['slug'] = slug
    user_info['cta_url'] = cta_url
    user_info['share_url'] = share_url
    if tombola_url:
        user_info['tombola_url'] = tombola_url
    if video_url:
        user_info['video_url'] = video_url
    if sharity_news_value:
        user_info['announcement'] = sharity_news_value

    save_json(pass_path, payload)

if __name__ == '__main__':
    main()
