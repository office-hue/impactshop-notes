# WordPress File Manager API - Használati útmutató

## Telepítés

1. **Másold a `wp-file-manager.php` fájlt** a WordPress mu-plugins mappába:
   ```
   /wp-content/mu-plugins/wp-file-manager.php
   ```

2. **Automatikusan aktiválódik** (mu-plugin)

## Használat

### 1. Fájl letöltése
```bash
# cURL példa
curl -X GET "https://yourdomain.com/wp-json/wp-manager/v1/download/snippets/my-snippet.php" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 2. Fájl feltöltése (automatikus backup)
```bash
# Fájl tartalmát base64-ben kell küldeni
curl -X POST "https://yourdomain.com/wp-json/wp-manager/v1/upload/snippets/my-snippet.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"content":"PD9waHAKLy8gSXR0IGEgUEhQIGvDs2Q="}'
```

### 3. Backup visszaállítása
```bash
curl -X POST "https://yourdomain.com/wp-json/wp-manager/v1/restore/snippets/my-snippet.php" \
  -H "Content-Type: application/json" \
  -d '{"timestamp":"2025-01-30_14-30-15"}'
```

### 4. Backup lista
```bash
curl -X GET "https://yourdomain.com/wp-json/wp-manager/v1/backups"
```

## Támogatott fájl típusok

- **snippets**: `/wp-content/snippets/`
- **mu-plugins**: `/wp-content/mu-plugins/`
- **plugins**: `/wp-content/plugins/`
- **themes**: `/wp-content/themes/[active-theme]/`

## Biztonsági funkciók

✅ **Automatikus backup** minden módosítás előtt  
✅ **PHP syntax check** - hibás kód esetén visszaállítás  
✅ **Permission check** - csak admin jogú felhasználók  
✅ **Timestamp alapú backup-ok**  
✅ **Visszaállítás lehetőség**  

## Példa használat Python-ból

```python
import requests
import base64

def download_snippet(filename, site_url, token=None):
    headers = {}
    if token:
        headers['Authorization'] = f'Bearer {token}'
    
    response = requests.get(f'{site_url}/wp-json/wp-manager/v1/download/snippets/{filename}', headers=headers)
    
    if response.status_code == 200:
        data = response.json()
        return base64.b64decode(data['content']).decode('utf-8')
    return None

def upload_snippet(filename, content, site_url, token=None):
    headers = {'Content-Type': 'application/json'}
    if token:
        headers['Authorization'] = f'Bearer {token}'
    
    data = {
        'content': base64.b64encode(content.encode('utf-8')).decode('utf-8')
    }
    
    response = requests.post(f'{site_url}/wp-json/wp-manager/v1/upload/snippets/{filename}', 
                           json=data, headers=headers)
    
    return response.json() if response.status_code == 200 else None

# Használat
site_url = "https://yourdomain.com"
code = download_snippet("my-snippet.php", site_url)
print(code)

# Módosítás után feltöltés
modified_code = code.replace("old_function", "new_function")
result = upload_snippet("my-snippet.php", modified_code, site_url)
print(result)
```

## ChatGPT integrációhoz

A ChatGPT ezekkel a függvényekkel tud dolgozni:

```python
# Letöltés
content = download_wp_file('snippets', 'impact-bridge.php', 'https://app.sharity.hu')

# Módosítás
modified_content = content.replace('old_code', 'new_code')

# Feltöltés (automatikus backup + syntax check)
result = upload_wp_file('snippets', 'impact-bridge.php', modified_content, 'https://app.sharity.hu')

if result['success']:
    print("Sikeres feltöltés!")
else:
    print("Hiba:", result['error'])
```

## Fatal error esetén

Ha valamit elrontasz:

1. **Nézd meg a backup listát**:
   ```
   GET /wp-json/wp-manager/v1/backups
   ```

2. **Állítsd vissza a legutóbbi working verziót**:
   ```
   POST /wp-json/wp-manager/v1/restore/snippets/filename.php
   {"timestamp": "2025-01-30_14-30-15"}
   ```

## WordPress REST API Auth

**Option 1**: Application Passwords (WP 5.6+)
- User Profile → Application Passwords → Add New
- Használd a generált jelszót Bearer tokenként

**Option 2**: JWT Plugin
- Telepítsd a JWT Authentication plugint
- Konfiguráld a secret key-t

**Option 3**: Cookie Auth (admin felületről)
- Ha már be vagy jelentkezve adminként, a cookie auth automatikus

---

**Előnyök:**
- ✅ Automatikus backup minden módosításnál
- ✅ PHP syntax validation
- ✅ Biztonságos rollback
- ✅ REST API alapú (bármilyen kliensből használható)
- ✅ Pluginok, snippetek, mu-pluginok mind kezelhetők