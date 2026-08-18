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

  function getFavPoems() {
    if (!(FAV_POEM_KEY in sessionStorage)) {
      return [];
    }
    try {
      const favPoems = JSON.parse(sessionStorage.getItem(FAV_POEM_KEY));
      return Array.isArray(favPoems) ? favPoems : [];
    }
    catch (e) {
      return [];
    }
  }

  function setFavPoems(favPoems) {
    sessionStorage.setItem(FAV_POEM_KEY, JSON.stringify(favPoems));
  }

  function removeFavPoem(poemId) {
    setFavPoems(getFavPoems().filter((poem) => String(poem.poemId) !== String(poemId)));
  }

  function storeFavPoem(poem) {
    const favPoems = getFavPoems();
    if (favPoems.some((item) => String(item.poemId) === String(poem.poemId))) {
      return;
    }
    favPoems.push(poem);
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

  function setHeartState(heart, isFavourite) {
    heart.src = `${HEART_BASE}-${isFavourite ? 'full' : 'outline'}.png`;
    heart.setAttribute('aria-pressed', isFavourite ? 'true' : 'false');
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
        removeFavPoem(button.getAttribute('data-id'));
        button.closest('li')?.remove();
        highlightFavourite();
        renderDropdownList();
        document.querySelectorAll('.heart[data-poem-id]').forEach((heart) => {
          if (String(heart.dataset.poemId) === String(button.getAttribute('data-id'))) {
            setHeartState(heart, false);
          }
        });
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
      const poemId = article.getAttribute('data-history-node-id');
      const body = article.querySelector('.block-field-blocknodepoembody .field__item, .block-field-blocknodepoembody .field--name-body');
      if (!poemId || !body || body.querySelector('.heart')) {
        return;
      }

      const title = document.querySelector('#block-pagetitle h1, h1.title')?.textContent.trim() || '';
      const poet = article.querySelector('.author .name, .author h3.name')?.textContent.trim() || '';
      const poemPath = window.location.pathname;
      const isFavourite = getFavPoems().some((poem) => String(poem.poemId) === String(poemId));

      const container = document.createElement('div');
      container.className = 'heart-container';

      const heart = document.createElement('img');
      heart.className = 'heart';
      heart.dataset.poemId = poemId;
      heart.dataset.title = title;
      heart.dataset.poet = poet;
      heart.dataset.poemPath = poemPath;
      heart.alt = isFrench() ? 'Ajouter aux favoris' : 'Add to favourites';
      setHeartState(heart, isFavourite);

      heart.addEventListener('click', (event) => {
        event.preventDefault();
        const currentlyFavourite = getFavPoems().some((poem) => String(poem.poemId) === String(poemId));
        if (currentlyFavourite) {
          removeFavPoem(poemId);
          setHeartState(heart, false);
        }
        else {
          storeFavPoem(poemFromHeart(heart));
          setHeartState(heart, true);
        }
        highlightFavourite();
      });

      container.appendChild(heart);
      body.appendChild(container);
    });
  }

  Drupal.behaviors.poemFavourites = {
    attach(context) {
      initHeader(context);
      initPoemHearts(context);
    },
  };
})(Drupal, once, drupalSettings);
