/* ==========================================================================
   Qatar Unseen — service worker.

   The whole strategy is two rules and one refusal, and the refusal is the most
   important of the three:

     assets/   cache-first. Every one of those URLs carries ?v=<mtime>, so a
                            changed file is a different URL and a cached copy can
                            never be stale — which is exactly the condition that
                            makes cache-first safe here and unsafe almost
                            everywhere else.

     pages     network-first, falling back to the copy we last saw. Online, the
                            visitor always gets the current page; offline, they
                            get the one they read last time.

     admin/,   never touched. The panel writes to the database, and a replayed
     api/,                  form submission or a cached JSON answer is worse than
     setup/                 having no offline support at all. Passed straight
                            through to the network.

   Two things it deliberately does not do:

   - It does not cache URLs with a query string. Share links (?c=…), the map's
     ?mark= lists and img.php?c=… are different for every visitor and every
     share, so caching them would grow without bound and eventually evict
     everything worth keeping. They go to the network, or they do not load.

   - It does not precache the site. The versioned asset URLs are only known to
     PHP, so there is no list of URLs to download at install time. What gets
     cached is what the visitor actually visits.
   ========================================================================== */

'use strict';

/* Bump this when the strategy below changes — not when the site changes, which
   the asset URLs already handle on their own. Everything under the old prefix
   is deleted on activation, so a bump is also the way to clear a bad cache. */
const VERSION = 'qatar-v1';
const ASSET_CACHE = VERSION + '-assets';
const PAGE_CACHE  = VERSION + '-pages';

/* The page shown when a navigation fails with nothing cached to fall back on.
   A plain .html file with no PHP in it, because the one situation it exists for
   is the one where the server cannot be reached. */
const OFFLINE_URL = new URL('offline.html', self.location.href).href;

/* Where this app lives, taken from the script's own URL — sw.js sits at the root
   of the scope it controls, so the directory above it is the scope. Used instead
   of self.registration.scope so that nothing here depends on the registration
   having been read back yet. */
const SCOPE_PATH = new URL('.', self.location.href).pathname;
const SCOPE_ORIGIN = new URL(self.location.href).origin;

/* How many pages to keep. Small on purpose: a browser gives an origin a few
   megabytes, losing the twentieth-most-recent page somebody read costs nothing,
   and an uncapped cache is one the browser eventually clears in full. */
const PAGE_LIMIT = 12;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(PAGE_CACHE)
      // cache: 'reload' so a stale HTTP-cached copy is not what gets stored.
      .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
      .catch(() => { /* nothing to store; the fetch handler below still works */ })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((names) => Promise.all(
        names
          .filter((name) => name !== ASSET_CACHE && name !== PAGE_CACHE)
          .map((name) => caches.delete(name))
      ))
      .then(() => self.clients.claim())
  );
});

/** Keep a cache from growing forever. Oldest first, which is the order the Cache
 *  API hands entries back in. */
function trim(cacheName, max) {
  return caches.open(cacheName).then((cache) =>
    cache.keys().then((keys) => {
      if (keys.length <= max) { return undefined; }
      return Promise.all(keys.slice(0, keys.length - max).map((k) => cache.delete(k)));
    })
  );
}

/** Same origin, at or below the path this worker controls. */
function inScope(url) {
  return url.origin === SCOPE_ORIGIN && url.pathname.startsWith(SCOPE_PATH);
}

/** Paths that must always reach the server, or not be answered at all. */
function isServerOnly(url) {
  const rest = url.pathname.slice(SCOPE_PATH.length);
  return rest.startsWith('admin/')
      || rest.startsWith('api/')
      || rest.startsWith('setup/')
      || rest.startsWith('inc/');
}

function cacheFirst(request) {
  return caches.open(ASSET_CACHE).then((cache) =>
    cache.match(request).then((hit) => {
      if (hit) { return hit; }
      return fetch(request).then((res) => {
        // Only a real, same-origin, successful response is worth keeping. An
        // opaque response cannot be inspected, and a 404 cached today is a 404
        // tomorrow.
        if (res && res.ok && res.type === 'basic') {
          cache.put(request, res.clone());
        }
        return res;
      });
    })
  );
}

/**
 * @param {FetchEvent} event passed in so the cache write can be handed to
 *        waitUntil — otherwise the worker may be shut down between the response
 *        reaching the page and the copy being stored, and the page is never
 *        cached however many times it is visited.
 */
function networkFirst(request, event) {
  return fetch(request)
    .then((res) => {
      // Only pages with no query string are kept: ?c=CODE and ?mark=… are
      // different for every visitor and every share, and while the cap below
      // would stop them growing without bound, filling the twelve slots with
      // other people's share links is not what offline support is for.
      const keep = res && res.ok && res.type === 'basic'
        && new URL(request.url).search === '';

      if (keep) {
        event.waitUntil(
          caches.open(PAGE_CACHE)
            .then((cache) => cache.put(request, res.clone()))
            .then(() => trim(PAGE_CACHE, PAGE_LIMIT))
            .catch(() => { /* a full or unavailable cache must not fail the page */ })
        );
      }
      return res;
    })
    .catch(() =>
      caches.match(request).then((hit) =>
        hit || caches.match(OFFLINE_URL).then((offline) => offline || Response.error())
      )
    );
}

self.addEventListener('fetch', (event) => {
  const request = event.request;

  // A POST is a form submission or a save. Never cached, never replayed.
  if (request.method !== 'GET') { return; }

  const url = new URL(request.url);

  // Fonts and anything else off-origin: the browser's own HTTP cache knows more
  // about them than this one would.
  if (!inScope(url) || isServerOnly(url)) { return; }

  if (url.pathname.startsWith(SCOPE_PATH + 'assets/')) {
    event.respondWith(cacheFirst(request));
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(networkFirst(request, event));
    return;
  }

  // Everything else — img.php, the API endpoints, anything with a query — is
  // fetched live, and only falls back to a stored copy when the network fails.
  event.respondWith(
    fetch(request).catch(() => caches.match(request).then((hit) => hit || Response.error()))
  );
});
