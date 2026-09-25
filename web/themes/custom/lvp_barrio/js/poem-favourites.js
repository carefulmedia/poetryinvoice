/**
 * @file
 * Poem favourites UI (cookie for anonymous, user account when logged in).
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  const HEART_BASE = '/roulette/images/icons/heart';
  const STORAGE_PATH = '/modules/custom/piv_base/js/poem-favourites-storage.js';

  let storage = window.PivPoemFavouritesStorage || null;
  let storagePromise = null;
  let favouritesCache = [];
  let settingsCache = null;
  let initPromise = null;

  function getStorage() {
    if (storage) {
      return Promise.resolve(storage);
    }
    if (!storagePromise) {
      storagePromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = STORAGE_PATH;
        script.onload = () => {
          storage = window.PivPoemFavouritesStorage || null;
          if (storage) {
            resolve(storage);
            return;
          }
          reject(new Error('Poem favourites storage failed to initialize.'));
        };
        script.onerror = () => reject(new Error('Poem favourites storage failed to load.'));
        document.head.appendChild(script);
      });
    }
    return storagePromise;
  }

  function getSettings() {
    return settingsCache || drupalSettings.poemFavourites || {};
  }

  function normalizePath(path) {
    if (storage && storage.normalizePoemPath) {
      return storage.normalizePoemPath(path);
    }
    if (!path) {
      return '';
    }
    return String(path).replace(/\/$/, '') || '/';
  }

  function poemPageUrl(path) {
    const normalized = normalizePath(path);
    return normalized ? `${window.location.origin}${normalized}` : path;
  }

  function poemKey(poem) {
    return normalizePath(poem.poemPath || poem.poemId || '');
  }

  function ensureLoaded() {
    if (!initPromise) {
      settingsCache = drupalSettings.poemFavourites || {};
      initPromise = getStorage().then((loadedStorage) => {
        storage = loadedStorage;
        return storage.ensureSettings(settingsCache).then((settings) => {
          settingsCache = settings;
          return storage.getFavourites(settings).then((list) => {
            favouritesCache = list;
            return list;
          });
        });
      });
    }
    return initPromise;
  }

  function applyFavourites(list) {
    favouritesCache = list;
    highlightFavourite();
    return list;
  }

  function refreshCache() {
    if (!storage || !storage.refreshFavourites) {
      return Promise.resolve(favouritesCache);
    }
    return storage.refreshFavourites(getSettings()).then(applyFavourites);
  }

  function handleSaveError(error) {
    console.error(error);
  }

  function initCrossTabSync() {
    if (!storage.subscribeFavourites) {
      return;
    }
    storage.subscribeFavourites((list) => {
      applyFavourites(list);
      document.querySelectorAll('.heart.poem-favourite-heart').forEach((heart) => {
        const key = storage.normalizePoemPath(heart.dataset.poemId || heart.dataset.poemPath || '');
        if (key) {
          setHeartState(heart, isFavourite(key));
        }
      });
    });
    const syncWhenActive = () => {
      if (storage.isLoggedIn(getSettings())) {
        refreshCache().catch(handleSaveError);
      }
      else {
        applyFavourites(storage.readFavouritesSync(getSettings()) || []);
      }
    };
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        syncWhenActive();
      }
    });
    window.addEventListener('focus', syncWhenActive);
    window.addEventListener('pageshow', syncWhenActive);
  }

  function getFavPoems() {
    if (storage && storage.readFavouritesSync) {
      const sync = storage.readFavouritesSync(getSettings());
      if (sync) {
        return sync;
      }
    }
    return favouritesCache;
  }

  function isFavourite(key) {
    const normalizedKey = normalizePath(key);
    return getFavPoems().some((poem) => poemKey(poem) === normalizedKey);
  }

  function removeFavPoem(key) {
    return storage.removeFavourite(getSettings(), key).then(applyFavourites);
  }

  function storeFavPoem(poem) {
    const key = poemKey(poem);
    if (!key || isFavourite(key)) {
      return Promise.resolve();
    }
    return storage.addFavourite(getSettings(), poem).then((saved) => {
      applyFavourites(saved);
      return saved;
    });
  }

  function isFrench() {
    const settings = getSettings();
    if (settings.lang) {
      return settings.lang === 'fr';
    }
    return window.location.hostname.indexOf('lesvoixdelapoesie') !== -1;
  }

  function highlightFavourite() {
    const button = document.getElementById('favouritesButton');
    if (!button) {
      return;
    }
    const favPoems = getFavPoems();
    const heart = favPoems.length ? 'full' : 'outline';
    const count = favPoems.length ? `<span class="fav-number">${favPoems.length}</span>` : '';
    button.innerHTML = `<img src="${HEART_BASE}-${heart}.png" alt="" style="height:28px;filter:grayscale(100%) brightness(2000%);" /> ${count}`;
  }

  function emptyMessage() {
    const settings = getSettings();
    if (isFrench()) {
      return settings.emptyFr || [
        'Trouvez un poème que vous aimez.',
        'Ensuite, cliquez sur le cœur à côté du poème pour l&rsquo;ajouter à vos favoris.',
        'Pour voir vos favoris, cliquez sur le cœur dans la barre de navigation.',
      ];
    }
    return settings.emptyEn || [
      'Find a poem you like.',
      'Click its heart to add it to My Favourites.',
      'To view My Favourites, click this heart.',
    ];
  }

  function renderDropdownList() {
    const list = document.getElementById('favouritePoems');
    if (!list) {
      return;
    }
    const favPoems = getFavPoems();
    list.innerHTML = '';
    if (!favPoems.length) {
      emptyMessage().forEach((line) => {
        const item = document.createElement('li');
        item.innerHTML = line;
        list.appendChild(item);
      });
      return;
    }
    favPoems.forEach((poem) => {
      const item = document.createElement('li');
      item.innerHTML = `<button type="button" class="poem-delete" data-id="${poem.poemId}"><span>&times;</span></button><a href="${poemPageUrl(poem.poemPath)}">${poem.title} - ${poem.poet}</a>`;
      list.appendChild(item);
    });
  }

  function toggleDropdown(forceOpen) {
    const dropdown = document.getElementById('favouritesDropdown');
    if (!dropdown) {
      return;
    }
    const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : dropdown.hidden || dropdown.style.display === 'none';
    if (shouldOpen) {
      const open = () => {
        renderDropdownList();
        dropdown.hidden = false;
        dropdown.style.display = 'block';
      };
      if (storage && storage.isLoggedIn(getSettings())) {
        refreshCache().then(open).catch(open);
      }
      else if (storage && storage.readFavouritesSync) {
        applyFavourites(storage.readFavouritesSync(getSettings()) || []);
        open();
      }
      else {
        open();
      }
      return;
    }
    else {
      dropdown.hidden = true;
      dropdown.style.display = 'none';
    }
  }

  function poemFromHeart(heart) {
    return {
      poemId: heart.dataset.poemId,
      title: heart.dataset.title,
      poet: heart.dataset.poet,
      poemPath: heart.dataset.poemPath,
    };
  }

  function setHeartState(heart, isFavourited) {
    heart.src = `${HEART_BASE}-${isFavourited ? 'full' : 'outline'}.png`;
    heart.setAttribute('aria-pressed', isFavourited ? 'true' : 'false');
  }

  function syncHeartStates(key, isFavourited) {
    const normalizedKey = normalizePath(key);
    document.querySelectorAll('.heart[data-poem-id], .heart[data-index]').forEach((heart) => {
      const heartKey = normalizePath(heart.dataset.poemId || heart.dataset.index || '');
      if (heartKey === normalizedKey) {
        setHeartState(heart, isFavourited);
      }
    });
  }

  function initHeader(context) {
    once('poem-favourites-header', '#favouritesButton', context).forEach((button) => {
      highlightFavourite();
      button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const dropdown = document.getElementById('favouritesDropdown');
        toggleDropdown(dropdown && (dropdown.hidden || dropdown.style.display === 'none'));
      });
    });

    once('poem-favourites-delete', 'body', context).forEach((body) => {
      body.addEventListener('click', (event) => {
        const button = event.target.closest('.poem-delete');
        if (!button) {
          return;
        }
        event.preventDefault();
        const key = button.getAttribute('data-id');
        removeFavPoem(key).then(() => {
          button.closest('li')?.remove();
          highlightFavourite();
          renderDropdownList();
          syncHeartStates(key, false);
        }).catch(handleSaveError);
      });
    });

    once('poem-favourites-outside-click', 'body', context).forEach((body) => {
      body.addEventListener('click', (event) => {
        const dropdown = document.getElementById('favouritesDropdown');
        if (!dropdown || dropdown.hidden) {
          return;
        }
        if (!event.target.closest('.poem-favourites')) {
          toggleDropdown(false);
        }
      });
    });
  }

  function initPoemHearts(context) {
    once('poem-favourites-heart-init', 'article.node--type-poem', context).forEach((article) => {
      if (article.querySelector('.heart.poem-favourite-heart')) {
        return;
      }

      const title = document.querySelector('#block-pagetitle h1, h1.title')?.textContent.trim() || '';
      const poet = article.querySelector('.author .name, .author h3.name')?.textContent.trim() || '';
      const poemPath = normalizePath(window.location.pathname);
      if (!poemPath) {
        return;
      }

      const heart = document.createElement('img');
      heart.className = 'heart poem-favourite-heart';
      heart.dataset.poemId = poemPath;
      heart.dataset.title = title;
      heart.dataset.poet = poet;
      heart.dataset.poemPath = poemPath;
      heart.alt = isFrench() ? 'Ajouter aux favoris' : 'Add to favourites';
      setHeartState(heart, isFavourite(poemPath));

      heart.addEventListener('click', (event) => {
        event.preventDefault();
        const runToggle = () => {
          const toggle = isFavourite(poemPath)
            ? removeFavPoem(poemPath).then(() => false)
            : storeFavPoem(poemFromHeart(heart)).then(() => true);
          toggle.then((isFavourited) => {
            setHeartState(heart, isFavourited);
            highlightFavourite();
            syncHeartStates(poemPath, isFavourited);
          }).catch(() => {
            handleSaveError(new Error('Unable to save poem favourites.'));
            setHeartState(heart, isFavourite(poemPath));
            highlightFavourite();
          });
        };
        if (storage.isLoggedIn(getSettings())) {
          refreshCache().then(runToggle).catch(runToggle);
        }
        else {
          runToggle();
        }
      });

      const printIcon = article.querySelector('.print-icon');
      if (printIcon) {
        printIcon.insertAdjacentElement('beforebegin', heart);
      }
      else {
        article.insertBefore(heart, article.firstChild);
      }
    });
  }

  Drupal.behaviors = Drupal.behaviors || {};
  Drupal.behaviors.poemFavourites = {
    attach(context) {
      ensureLoaded().then(() => {
        initCrossTabSync();
        initHeader(context);
        initPoemHearts(context);
        highlightFavourite();
      }).catch((error) => {
        console.error(error);
      });
    },
  };
})(window.Drupal = window.Drupal || {}, window.once, window.drupalSettings || {});
