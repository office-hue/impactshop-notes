-- ImpactShop Badge Definitions Fix
-- 2026-01-29
-- 
-- 21 badge definition hiányzik az wp_impact_badge_definitions táblából,
-- ezért a Legacy Wall ezeket slug-gal jeleníti meg (pl. votes_10) label helyett.
--
-- Ez a script hozzáadja a hiányzó definíciókat.
-- A kód (impact-gamification.php) ezeket a badge_key-eket osztja ki.
--
-- FUTTATÁS ELŐTT: Készíts backupot!

-- Hiányzó badge-ek listája (seed vs API összehasonlítás alapján):
-- votes_10, votes_5000, votes_10000
-- views_1, views_1000, views_5000, views_10000
-- streak_365
-- ngo_1, ngo_10, ngo_100
-- offers_10, offers_100, offers_500, offers_1000
-- edu_complete_50, edu_complete_100
-- anniversary_2, anniversary_3, anniversary_4, anniversary_5

INSERT INTO wp_impact_badge_definitions 
    (badge_key, category, name_hu, description_hu, default_tier, is_active, sort_order)
VALUES
    -- Szavazás badge-ek
    ('votes_10', 'tamogatas', '10 szavazat', '10 leadott szavazat.', 'silver', 1, 0),
    ('votes_5000', 'tamogatas', '5000 szavazat', '5000 leadott szavazat.', 'diamond', 1, 0),
    ('votes_10000', 'tamogatas', '10000 szavazat', '10000 leadott szavazat.', 'legend', 1, 0),
    
    -- Videó nézés badge-ek
    ('views_1', 'aktivitas', 'Első videó', '1 megtekintett videó.', 'bronze', 1, 0),
    ('views_1000', 'aktivitas', '1000 videó', '1000 megtekintett videó.', 'platinum', 1, 0),
    ('views_5000', 'aktivitas', '5000 videó', '5000 megtekintett videó.', 'diamond', 1, 0),
    ('views_10000', 'aktivitas', '10000 videó', '10000 megtekintett videó.', 'legend', 1, 0),
    
    -- Streak badge
    ('streak_365', 'aktivitas', '365 napos streak', 'Egymás után 365 nap aktivitás.', 'diamond', 1, 0),
    
    -- NGO badge-ek
    ('ngo_1', 'tamogatas', 'Első szervezet', 'Első támogatott szervezet.', 'bronze', 1, 0),
    ('ngo_10', 'tamogatas', '10 szervezet', '10 különböző szervezet támogatása.', 'silver', 1, 0),
    ('ngo_100', 'tamogatas', '100 szervezet', '100 különböző szervezet támogatása.', 'gold', 1, 0),
    
    -- Offerwall badge-ek (a kód ezeket adja, nem offers_5/20)
    ('offers_10', 'offerwall', '10 offer', '10 offerwall teljesítés.', 'silver', 1, 0),
    ('offers_100', 'offerwall', '100 offer', '100 offerwall teljesítés.', 'gold', 1, 0),
    ('offers_500', 'offerwall', '500 offer', '500 offerwall teljesítés.', 'platinum', 1, 0),
    ('offers_1000', 'offerwall', '1000 offer', '1000 offerwall teljesítés.', 'diamond', 1, 0),
    
    -- Edukáció badge-ek
    ('edu_complete_50', 'tanulas', '50 edukáció', '50 edukációs videó.', 'platinum', 1, 0),
    ('edu_complete_100', 'tanulas', '100 edukáció', '100 edukációs videó.', 'diamond', 1, 0),
    
    -- Évforduló badge-ek
    ('anniversary_2', 'kozosseg', '2 éves évforduló', '2 éve Sharity tag.', 'silver', 1, 0),
    ('anniversary_3', 'kozosseg', '3 éves évforduló', '3 éve Sharity tag.', 'gold', 1, 0),
    ('anniversary_4', 'kozosseg', '4 éves évforduló', '4 éve Sharity tag.', 'platinum', 1, 0),
    ('anniversary_5', 'kozosseg', '5 éves évforduló', '5 éve Sharity tag.', 'legend', 1, 0)
ON DUPLICATE KEY UPDATE
    name_hu = VALUES(name_hu),
    description_hu = VALUES(description_hu),
    default_tier = VALUES(default_tier),
    is_active = 1;

-- Ellenőrzés:
-- SELECT badge_key, name_hu, is_active FROM wp_impact_badge_definitions ORDER BY badge_key;
