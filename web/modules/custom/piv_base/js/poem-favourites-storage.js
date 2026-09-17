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

  function hasDrupalSession() {
    return /(?:^|;\s*)(?:SSESS|SESS)[\w-]*=/.test(document.cookie);
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
    if (base.uid > 0 && Array.isArray(base.favourites)) {
      return { ...base, _bootstrapped: true };
    }
    if (base.uid > 0 && base.apiUrl) {
      const response = await fetch(base.apiUrl, { credentials: 'same-origin' });
      if (response.ok) {
        return {
          ...base,
          favourites: normalizeList(await response.json()),
          _bootstrapped: true,
        };
      }
    }
    // Drupal pages pass uid: 0 for anonymous users; cookie storage needs no API.
    if ('uid' in base && base.uid === 0) {
      return { ...base, _bootstrapped: true };
    }
    // Roulette / standalone: bootstrap only when a Drupal login session exists.
    if (!hasDrupalSession()) {
      return { ...base, uid: 0, _bootstrapped: true };
    }
    return { ...await fetchBootstrap({ ...base, uid: base.uid || 0 }), _bootstrapped: true };
  }

  async function fetchFavourites(settings) {
    const ready = await ensureSettings(settings);
    if (isLoggedIn(ready)) {
      if (!ready.apiUrl) {
        return [];
      }
      const response = await fetch(ready.apiUrl, { credentials: 'same-origin' });
      if (!response.ok) {
        return [];
      }
      return normalizeList(await response.json());
    }
    return getCookieFavourites();
  }

  async function loadUserFavourites(settings) {
    if (Array.isArray(settings.favourites)) {
      return normalizeList(settings.favourites);
    }
    return fetchFavourites(settings);
  }

  let favouritesChannel = null;
  function getFavouritesChannel() {
    if (favouritesChannel === null && typeof BroadcastChannel !== 'undefined') {
      try {
        favouritesChannel = new BroadcastChannel('piv-poem-favourites');
      }
      catch (e) {
        favouritesChannel = false;
      }
    }
    return favouritesChannel || null;
  }

  function notifyFavouritesChanged(favourites) {
    getFavouritesChannel()?.postMessage({
      favourites: normalizeList(favourites),
    });
  }

  let writeLock = Promise.resolve();

  function withWriteLock(task) {
    const run = writeLock.catch(() => {}).then(task);
    writeLock = run.catch(() => {});
    return run;
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
      throw new Error(`Unable to save poem favourites (${response.status}).`);
    }
    const saved = normalizeList(await response.json());
    notifyFavouritesChanged(saved);
    return saved;
  }

  async function addFavourite(settings, poem) {
    return withWriteLock(async () => {
      const ready = await ensureSettings(settings);
      const current = await fetchFavourites(ready);
      const key = normalizePoemPath(poem.poemPath || poem.poemId || '');
      if (!key || current.some((entry) => normalizePoemPath(entry.poemPath || entry.poemId) === key)) {
        return current;
      }
      const next = current.concat([{
        poemId: key,
        poemPath: key,
        title: (poem.title || '').trim(),
        poet: (poem.poet || '').trim(),
      }]);
      return storage.setFavourites(ready, next);
    });
  }

  async function removeFavourite(settings, key) {
    return withWriteLock(async () => {
      const ready = await ensureSettings(settings);
      const normalizedKey = normalizePoemPath(key);
      const current = await fetchFavourites(ready);
      const next = current.filter((entry) => normalizePoemPath(entry.poemPath || entry.poemId) !== normalizedKey);
      return storage.setFavourites(ready, next);
    });
  }

  const storage = {
    normalizePoemPath,
    normalizeList,
    isLoggedIn,
    ensureSettings,
    fetchBootstrap,
    refreshFavourites(settings) {
      return fetchFavourites(settings);
    },
    subscribeFavourites(callback) {
      const channel = getFavouritesChannel();
      if (!channel) {
        return () => {};
      }
      const handler = (event) => {
        if (Array.isArray(event.data?.favourites)) {
          callback(normalizeList(event.data.favourites));
        }
      };
      channel.addEventListener('message', handler);
      return () => channel.removeEventListener('message', handler);
    },
    getFavourites(settings) {
      if (isLoggedIn(settings)) {
        return loadUserFavourites(settings);
      }
      return Promise.resolve(getCookieFavourites());
    },
    addFavourite,
    removeFavourite,
    setFavourites(settings, favourites) {
      const normalized = normalizeList(favourites);
      if (isLoggedIn(settings)) {
        return saveUserFavourites(settings, normalized);
      }
      const saved = setCookieFavourites(normalized);
      notifyFavouritesChanged(saved);
      return Promise.resolve(saved);
    },
  };

  window.PivPoemFavouritesStorage = storage;
  Drupal.PivPoemFavouritesStorage = storage;
})(window.Drupal || {}, window.drupalSettings || {});
