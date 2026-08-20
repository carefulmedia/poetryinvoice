/**
 * @file
 * Poem favourites stored in sessionStorage (shared with Poem Roulette).
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  const FAV_POEM_KEY = 'favPoems';
  const HEART_BASE = '/roulette/images/icons/heart';

  function getSettings() {
    return drupalSettings.poemFavourites || {};
  }

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
      // Fall through to pathname normalization.
    }
    return String(path).replace(/\/$/, '') || '/';
  }

  function poemKey(poem) {
    return normalizePoemPath(poem.poemPath || poem.poemId || '');
  }

  function getFavPoems() {
    if (!(FAV_POEM_KEY in sessionStorage)) {
      return [];
    }
    try {
      const favPoems = JSON.parse(sessionStorage.getItem(FAV_POEM_KEY));
      if (!Array.isArray(favPoems)) {
        return [];
      }
      const seen = new Set();
      const normalized = [];
      favPoems.forEach((poem) => {
        const key = poemKey(poem);
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
    catch (e) {
      return [];
    }
  }

  function setFavPoems(favPoems) {
    sessionStorage.setItem(FAV_POEM_KEY, JSON.stringify(favPoems));
  }

  function isFavourite(key) {
    const normalizedKey = normalizePoemPath(key);
    return getFavPoems().some((poem) => poemKey(poem) === normalizedKey);
  }

  function removeFavPoem(key) {
    const normalizedKey = normalizePoemPath(key);
    setFavPoems(getFavPoems().filter((poem) => poemKey(poem) !== normalizedKey));
  }

  function storeFavPoem(poem) {
    const key = poemKey(poem);
    if (!key || isFavourite(key)) {
      return;
    }
    const favPoems = getFavPoems();
    favPoems.push({
      poemId: key,
      poemPath: key,
      title: (poem.title || '').trim(),
      poet: (poem.poet || '').trim(),
    });
    setFavPoems(favPoems);
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
    button.innerHTML = `<img src="${HEART_BASE}-${heart}.png" alt="" style="height:20px;filter:grayscale(100%) brightness(2000%);" /> ${count}`;
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
      item.innerHTML = `<button type="button" class="poem-delete" data-id="${poem.poemId}"><span>&times;</span></button><a href="${poem.poemPath}">${poem.title} - ${poem.poet}</a>`;
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
      renderDropdownList();
      dropdown.hidden = false;
      dropdown.style.display = 'block';
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
    const normalizedKey = normalizePoemPath(key);
    document.querySelectorAll('.heart[data-poem-id], .heart[data-index]').forEach((heart) => {
      const heartKey = normalizePoemPath(heart.dataset.poemId || heart.dataset.index || '');
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
        removeFavPoem(key);
        button.closest('li')?.remove();
        highlightFavourite();
        renderDropdownList();
        syncHeartStates(key, false);
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
      const poemPath = normalizePoemPath(window.location.pathname);
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
        if (isFavourite(poemPath)) {
          removeFavPoem(poemPath);
          setHeartState(heart, false);
        }
        else {
          storeFavPoem(poemFromHeart(heart));
          setHeartState(heart, true);
        }
        highlightFavourite();
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

  Drupal.behaviors.poemFavourites = {
    attach(context) {
      initHeader(context);
      initPoemHearts(context);
    },
  };
})(Drupal, once, drupalSettings);
