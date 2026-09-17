/**
 * @file
 * Poem favourites: cookie (anonymous) or user account (logged in).
 *
 * Cookie and user storage are independent; logged-in users never read/write
 * the cookie. On logout, any existing cookie favourites are shown again.
 */
(function (Drupal, drupalSettings) {
  'use strict';

  Drupal = Drupal || {};
  drupalSettings = drupalSettings || {};

  const COOKIE_NAME = 'pivFavPoems';
  const LEGACY_SESSION_KEY = 'favPoems';
  const COOKIE_MAX_AGE = 31536000;

  function normalizePoemPath(path) {
    if (!path) {
      return '';
    }
    try {
      if (/^https?:\/\//i.test(path)) {
        return new URL(path).pathname.replace(/\/$/, '') || '/';
      }
    }
    catch (e) {
      // Fall through.
    }
    return String(path).replace(/\/$/, '') || '/';
  }

  function normalizeList(favourites) {
    if (!Array.isArray(favourites)) {
      return [];
    }
    const seen = new Set();
    const normalized = [];
    favourites.forEach((poem) => {
      const key = normalizePoemPath(poem.poemPath || poem.poemId || '');
      if (!key || seen.has(key)) {
        return;
      }
      seen.add(key);
      normalized.push({
        poemId: key,
        poemPath: key,
        title: (poem.title || '').trim(),
        poet: (poem.poet || '').trim(),
      });
    });
    return normalized;
  }

  function readCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  }

  function writeCookie(name, value, maxAge) {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${maxAge}; Path=/; SameSite=Lax${secure}`;
  }

  function deleteCookie(name) {
    writeCookie(name, '', 0);
  }

  function migrateLegacySessionStorage() {
    try {
      if (!(LEGACY_SESSION_KEY in sessionStorage)) {
        return;
      }
      const legacy = JSON.parse(sessionStorage.getItem(LEGACY_SESSION_KEY));
      sessionStorage.removeItem(LEGACY_SESSION_KEY);
      if (!readCookie(COOKIE_NAME) && Array.isArray(legacy) && legacy.length) {
        writeCookie(COOKIE_NAME, JSON.stringify(normalizeList(legacy)), COOKIE_MAX_AGE);
      }
    }
    catch (e) {
      sessionStorage.removeItem(LEGACY_SESSION_KEY);
    }
  }

  function getCookieFavourites() {
    migrateLegacySessionStorage();
    const raw = readCookie(COOKIE_NAME);
    if (!raw) {
      return [];
    }
    try {
      return normalizeList(JSON.parse(raw));
    }
    catch (e) {
      deleteCookie(COOKIE_NAME);
      return [];
    }
  }

  function setCookieFavourites(favourites) {
    migrateLegacySessionStorage();
    const normalized = normalizeList(favourites);
    if (!normalized.length) {
      deleteCookie(COOKIE_NAME);
      return normalized;
    }
    writeCookie(COOKIE_NAME, JSON.stringify(normalized), COOKIE_MAX_AGE);
    return normalized;
  }

  function isLoggedIn(settings) {
    return Boolean(settings && settings.uid);
  }

  function applyBootstrap(settings, bootstrap) {
    if (!bootstrap || typeof bootstrap !== 'object') {
      return settings;
    }
    return {
      ...settings,
      uid: bootstrap.uid || 0,
      favourites: Array.isArray(bootstrap.favourites) ? bootstrap.favourites : settings.favourites,
      apiUrl: bootstrap.apiUrl || settings.apiUrl,
      csrfToken: bootstrap.csrfToken || settings.csrfToken,
    };
  }

  async function fetchBootstrap(settings) {
    const response = await fetch('/api/poem-favourites/bootstrap', {
      credentials: 'same-origin',
    });
    if (!response.ok) {
      return settings;
    }
    return applyBootstrap(settings, await response.json());
  }

  async function ensureSettings(settings) {
    const base = settings || (typeof drupalSettings !== 'undefined' ? drupalSettings.poemFavourites : {}) || {};
    if (base._bootstrapped) {
      return base;
    }
    if (base.uid && Array.isArray(base.favourites)) {
      return { ...base, _bootstrapped: true };
    }
    if (base.uid && base.apiUrl) {
      const response = await fetch(base.apiUrl, { credentials: 'same-origin' });
      if (response.ok) {
        return {
          ...base,
          favourites: normalizeList(await response.json()),
          _bootstrapped: true,
        };
      }
    }
    return { ...await fetchBootstrap(base), _bootstrapped: true };
  }

  async function loadUserFavourites(settings) {
    if (Array.isArray(settings.favourites)) {
      return normalizeList(settings.favourites);
    }
    const ready = await ensureSettings(settings);
    if (Array.isArray(ready.favourites)) {
      return normalizeList(ready.favourites);
    }
    if (!ready.apiUrl) {
      return [];
    }
    const response = await fetch(ready.apiUrl, { credentials: 'same-origin' });
    if (!response.ok) {
      return [];
    }
    return normalizeList(await response.json());
  }

  async function saveUserFavourites(settings, favourites) {
    const ready = await ensureSettings(settings);
    if (!ready.apiUrl || !ready.csrfToken) {
      return favourites;
    }
    const response = await fetch(ready.apiUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': ready.csrfToken,
      },
      body: JSON.stringify(favourites),
    });
    if (!response.ok) {
      throw new Error('Unable to save poem favourites.');
    }
    return normalizeList(await response.json());
  }

  const storage = {
    normalizePoemPath,
    normalizeList,
    isLoggedIn,
    ensureSettings,
    fetchBootstrap,
    getFavourites(settings) {
      if (isLoggedIn(settings)) {
        return loadUserFavourites(settings);
      }
      return Promise.resolve(getCookieFavourites());
    },
    setFavourites(settings, favourites) {
      const normalized = normalizeList(favourites);
      if (isLoggedIn(settings)) {
        return saveUserFavourites(settings, normalized);
      }
      return Promise.resolve(setCookieFavourites(normalized));
    },
  };

  window.PivPoemFavouritesStorage = storage;
  Drupal.PivPoemFavouritesStorage = storage;
})(Drupal, window.drupalSettings || {});
