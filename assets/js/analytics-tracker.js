/**
 * Кафе «Белоснежка» — Универсальный трекер целей и событий аналитики
 * Яндекс.Метрика ID: 113705981
 * Google Tag Manager ID: GTM-NQQ5N8SM
 */
(function() {
  'use strict';
  var YM_ID = 113705981;

  window.dataLayer = window.dataLayer || [];

  window.trackBelkaEvent = function(eventName, params) {
    params = params || {};
    try {
      // 1. Google Tag Manager / Yandex Tag Manager (DataLayer)
      var dlPayload = Object.assign({
        event: eventName,
        event_time: new Date().toISOString()
      }, params);
      window.dataLayer.push(dlPayload);

      // 2. Yandex.Metrika direct reachGoal
      if (typeof window.ym === 'function') {
        try {
          window.ym(YM_ID, 'reachGoal', eventName, params);
        } catch(e) {
          console.warn('[YM reachGoal error]', e);
        }
      }

      // 3. Google Analytics 4 (gtag) direct event
      if (typeof window.gtag === 'function') {
        try {
          window.gtag('event', eventName, params);
        } catch(e) {
          console.warn('[gtag event error]', e);
        }
      }

      // 3. E-commerce standard payload for dishes
      if (eventName === 'view_dish' && params.dish_name) {
        var rawPrice = String(params.dish_price || '').replace(/[^\d.]/g, '');
        var numPrice = parseFloat(rawPrice) || 0;
        window.dataLayer.push({
          event: 'view_item',
          ecommerce: {
            currencyCode: 'RUB',
            detail: {
              products: [{
                name: params.dish_name,
                price: numPrice,
                category: params.dish_category || 'Меню'
              }]
            },
            items: [{
              item_name: params.dish_name,
              price: numPrice,
              item_category: params.dish_category || 'Меню'
            }]
          }
        });
      }

      // DevTools log for easy verification
      console.log('[Belka Analytics Event]:', eventName, params);
    } catch(err) {
      console.warn('[Analytics Error]', err);
    }
  };

  // ── Global click tracking (delegation) ─────────────────────────────────
  document.addEventListener('click', function(e) {
    var target = e.target;
    if (!target) return;

    // 1. Phone clicks
    var phoneLink = target.closest('a[href^="tel:"]');
    if (phoneLink) {
      var tel = (phoneLink.getAttribute('href') || '').replace('tel:', '').trim();
      var pLoc = phoneLink.closest('header, nav') ? 'header' :
                 phoneLink.closest('.dish-modal-card') ? 'dish_modal' :
                 phoneLink.closest('footer') ? 'footer' : 'content';
      window.trackBelkaEvent('click_phone', { phone: tel, location: pLoc });
      return;
    }

    // 2. Booking button clicks
    var bookingBtn = target.closest('.booking-btn') ||
                     (target.closest('button, a') && target.closest('button, a').innerText && /забронировать/i.test(target.closest('button, a').innerText));
    if (bookingBtn) {
      var btnEl = target.closest('button, a') || bookingBtn;
      var text = (btnEl.innerText || '').trim();
      var bLoc = btnEl.closest('header, nav') ? 'header' :
                 btnEl.closest('#hero, .hero') ? 'hero' :
                 btnEl.closest('#halls, .halls') ? 'halls' :
                 btnEl.closest('.dish-modal-card') ? 'dish_modal' :
                 btnEl.closest('#promotions') ? 'promotions' :
                 btnEl.closest('#contacts, footer') ? 'contacts' : 'page';

      window.trackBelkaEvent('booking_click', {
        location: bLoc,
        button_text: text
      });

      if (bLoc === 'dish_modal') {
        window.trackBelkaEvent('dish_booking_click', { button_text: text });
      }
      return;
    }

    // 3. Social & Map links
    var extLink = target.closest('a[href]');
    if (extLink) {
      var href = extLink.getAttribute('href') || '';
      if (href.indexOf('vk.com') !== -1) {
        window.trackBelkaEvent('click_vk', { url: href });
      } else if (href.indexOf('yandex.ru/maps') !== -1 || href.indexOf('2gis') !== -1 || href.indexOf('google.com/maps') !== -1) {
        window.trackBelkaEvent('click_map', { url: href });
      }
    }
  }, true); // Capture phase ensures we always catch the click

})();
