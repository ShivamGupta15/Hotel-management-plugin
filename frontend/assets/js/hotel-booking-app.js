/**
 * Modern Reactive Hotel Booking Client Application
 */
(function () {
  'use strict';

  const HotelApp = {
    config: window.HotelMgmtConfig || {
      apiUrl: '/wp-json/hotel/v1',
      nonce: '',
      currencySymbol: '₹',
    },

    state: {
      checkIn: '',
      checkOut: '',
      adults: 2,
      children: 0,
      roomCount: 1,
      selectedRoom: null,
      currentHold: null,
      timerInterval: null,
    },

    init() {
      this.initDefaultDates();
      this.ensureModalExists();
      this.bindSearchEvents();
      this.bindBookingModalEvents();
      this.bindRoomCardBookButtons();
    },

    ensureModalExists() {
      let overlay = document.getElementById('hm-booking-modal');
      if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'hm-booking-modal';
        overlay.className = 'hm-modal-overlay';
        overlay.innerHTML = `
          <div class="hm-modal-container">
            <div class="hm-modal-header">
              <h3 class="hm-modal-title">Complete Your Reservation</h3>
              <button type="button" class="hm-modal-close" aria-label="Close">&times;</button>
            </div>
            <div id="hm-modal-content-slot" class="hm-modal-body"></div>
          </div>
        `;
        document.body.appendChild(overlay);
      }
      return overlay;
    },

    initDefaultDates() {
      const today = new Date();
      const tomorrow = new Date(today);
      tomorrow.setDate(tomorrow.getDate() + 2);

      const formatDate = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
      };

      this.state.checkIn = formatDate(today);
      this.state.checkOut = formatDate(tomorrow);

      const checkInInput = document.getElementById('hm-search-checkin');
      const checkOutInput = document.getElementById('hm-search-checkout');

      if (checkInInput && !checkInInput.value) checkInInput.value = this.state.checkIn;
      if (checkOutInput && !checkOutInput.value) checkOutInput.value = this.state.checkOut;
    },

    bindSearchEvents() {
      const searchForm = document.getElementById('hm-search-form');
      if (!searchForm) return;

      searchForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.performSearch();
      });
    },

    bindRoomCardBookButtons() {
      document.addEventListener('click', (e) => {
        const btn = e.target.closest('.hm-btn-book, [data-action="book-room"]');
        if (!btn) return;
        e.preventDefault();

        const roomId = btn.dataset.roomId;
        const roomTitle = btn.dataset.roomTitle || 'Luxury Suite';
        const roomPrice = parseFloat(btn.dataset.roomPrice || 0);

        this.openCheckoutModal({
          id: roomId,
          title: roomTitle,
          base_price: roomPrice,
        });
      });
    },

    bindBookingModalEvents() {
      document.addEventListener('click', (e) => {
        const overlay = document.getElementById('hm-booking-modal');
        if (!overlay) return;

        if (e.target === overlay || e.target.closest('.hm-modal-close')) {
          this.closeCheckoutModal();
        }
      });

      document.addEventListener('submit', (e) => {
        if (e.target && e.target.id === 'hm-checkout-form') {
          e.preventDefault();
          this.submitHoldAndPay();
        }
      });
    },

    buildApiUrl(endpoint, params = {}) {
      const base = (this.config.apiUrl || '/wp-json/hotel/v1').replace(/\/$/, '');
      const cleanEndpoint = endpoint.startsWith('/') ? endpoint : '/' + endpoint;
      let url = `${base}${cleanEndpoint}`;

      const queryKeys = Object.keys(params);
      if (queryKeys.length > 0) {
        const sep = url.includes('?') ? '&' : '?';
        const queryStr = queryKeys
          .map(k => `${encodeURIComponent(k)}=${encodeURIComponent(params[k])}`)
          .join('&');
        url += `${sep}${queryStr}`;
      }
      return url;
    },

    async performSearch() {
      const checkIn = document.getElementById('hm-search-checkin')?.value || this.state.checkIn;
      const checkOut = document.getElementById('hm-search-checkout')?.value || this.state.checkOut;
      const adults = document.getElementById('hm-search-adults')?.value || 2;
      const children = document.getElementById('hm-search-children')?.value || 0;
      const roomCount = document.getElementById('hm-search-rooms')?.value || 1;

      this.state.checkIn = checkIn;
      this.state.checkOut = checkOut;
      this.state.adults = adults;
      this.state.children = children;
      this.state.roomCount = roomCount;

      const container = document.getElementById('hm-rooms-results-container');
      if (!container) return;

      container.innerHTML = `
        <div style="grid-column: 1/-1; text-align: center; padding: 60px 0;">
          <div style="display:inline-block; width:40px; height:40px; border:3px solid #e2e8f0; border-top-color: #b45309; border-radius:50%; animation: spin 0.8s linear infinite;"></div>
          <p style="color:#64748b; margin-top: 14px; font-weight:500;">Searching available luxury suites...</p>
        </div>
        <style>@keyframes spin { 100% { transform: rotate(360deg); } }</style>
      `;

      try {
        const url = this.buildApiUrl('/availability', {
          check_in: checkIn,
          check_out: checkOut,
          adults: adults,
          children: children,
          room_count: roomCount,
        });

        console.log('[Hotel Management] Fetching availability from:', url);

        const res = await fetch(url, {
          headers: {
            'X-WP-Nonce': this.config.nonce || '',
          },
        });

        const data = await res.json();

        if (!res.ok) {
          throw new Error(data.message || (data.code ? `API Error: ${data.code}` : 'Unable to query availability service.'));
        }

        if (!data.success || !data.results || data.results.length === 0) {
          container.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; background: #f8fafc; border-radius: 16px; border: 1px dashed #cbd5e1;">
              <h3 style="font-family: serif; color:#0f172a; margin-bottom: 8px;">No Suites Available For Selected Dates</h3>
              <p style="color:#64748b;">Please select alternative dates or adjust guest capacity to discover availability.</p>
            </div>
          `;
          return;
        }

        this.renderRoomCards(data.results, container);
      } catch (err) {
        console.error('[Hotel Management] Search Error:', err);
        container.innerHTML = `<div style="grid-column: 1/-1; color: #ef4444; text-align: center; padding: 40px 20px; font-weight: 500;">Unable to search rooms: ${err.message}</div>`;
      }
    },

    renderRoomCards(rooms, container) {
      container.innerHTML = rooms.map(room => {
        const quote = room.price_quote;
        const amenities = (room.amenities_list || []).slice(0, 4).map(a => `<span class="hm-tag">${a}</span>`).join('');
        const nights = quote ? quote.total_nights : 1;
        const total = quote ? quote.total_amount : room.base_price;

        return `
          <article class="hm-room-card">
            <div class="hm-room-image-wrap">
              <img src="${room.featured_image || 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=800&q=80'}" alt="${room.title}" class="hm-room-image" loading="lazy" />
              <div class="hm-room-badge">${room.bed_type}</div>
              <div class="hm-room-avail-badge">${room.min_available_rooms} left</div>
            </div>
            <div class="hm-room-body">
              <h3 class="hm-room-title">${room.title}</h3>
              <div class="hm-room-specs">
                <span>👤 Up to ${room.max_occupancy} guests</span>
                <span>📐 ${room.size_sqft} sqft</span>
              </div>
              <p class="hm-room-desc">${room.short_description || room.description}</p>
              <div class="hm-amenities-tags">${amenities}</div>
              <div class="hm-room-footer">
                <div class="hm-price-box">
                  <span class="hm-price-amount">${this.config.currencySymbol}${Number(total).toLocaleString()}</span>
                  <span class="hm-price-period">Total for ${nights} night${nights > 1 ? 's' : ''} (incl. taxes)</span>
                </div>
                <button type="button" class="hm-btn-book"
                  data-room-id="${room.id}"
                  data-room-title="${room.title}"
                  data-room-price="${room.base_price}">
                  Book Suite
                </button>
              </div>
            </div>
          </article>
        `;
      }).join('');
    },

    openCheckoutModal(room) {
      this.ensureModalExists();
      this.state.selectedRoom = room;

      // Synchronize dates and guest count from search inputs if set
      const ci = document.getElementById('hm-search-checkin')?.value;
      const co = document.getElementById('hm-search-checkout')?.value;
      const ad = document.getElementById('hm-search-adults')?.value;
      const ch = document.getElementById('hm-search-children')?.value;
      const rm = document.getElementById('hm-search-rooms')?.value;

      if (ci) this.state.checkIn = ci;
      if (co) this.state.checkOut = co;
      if (ad) this.state.adults = ad;
      if (ch) this.state.children = ch;
      if (rm) this.state.roomCount = rm;

      const overlay = document.getElementById('hm-booking-modal');
      const body = document.getElementById('hm-modal-content-slot');
      if (!overlay || !body) return;

      body.innerHTML = `
        <div style="margin-bottom: 20px;">
          <h4 style="margin: 0 0 6px 0; font-size: 1.25rem; font-family: var(--hm-font-heading); color:#0f172a;">${room.title}</h4>
          <p style="margin: 0; font-size: 0.9rem; color:#64748b;">
            Dates: <strong>${this.state.checkIn}</strong> to <strong>${this.state.checkOut}</strong> &bull;
            Guests: <strong>${this.state.adults} Adults</strong> &bull; Rooms: <strong>${this.state.roomCount}</strong>
          </p>
        </div>

        <form id="hm-checkout-form">
          <div class="hm-form-row">
            <div class="hm-form-col">
              <label class="hm-form-label">Full Name *</label>
              <input type="text" name="guest_name" class="hm-form-input" required placeholder="e.g. Rahul Sharma" />
            </div>
            <div class="hm-form-col">
              <label class="hm-form-label">Email Address *</label>
              <input type="email" name="guest_email" class="hm-form-input" required placeholder="rahul@example.com" />
            </div>
          </div>
          <div class="hm-form-row">
            <div class="hm-form-col">
              <label class="hm-form-label">Phone Number *</label>
              <input type="tel" name="guest_phone" class="hm-form-input" required placeholder="+91 98765 43210" />
            </div>
            <div class="hm-form-col">
              <label class="hm-form-label">Special Requests</label>
              <input type="text" name="special_requests" class="hm-form-input" placeholder="Early check-in, high floor, etc." />
            </div>
          </div>

          <div style="margin-top: 24px;">
            <button type="submit" id="hm-submit-hold-btn" class="hm-btn-search" style="width: 100%; justify-content: center; padding: 14px; font-size: 1.05rem;">
              Lock Room & Proceed to Payment
            </button>
          </div>
        </form>
      `;

      overlay.classList.add('active');
    },

    closeCheckoutModal() {
      const overlay = document.getElementById('hm-booking-modal');
      if (overlay) overlay.classList.remove('active');
      if (this.state.timerInterval) clearInterval(this.state.timerInterval);
    },

    async submitHoldAndPay() {
      const form = document.getElementById('hm-checkout-form');
      const submitBtn = document.getElementById('hm-submit-hold-btn');
      const prevErr = document.getElementById('hm-checkout-error');
      if (prevErr) prevErr.remove();
      if (!form || !submitBtn) return;

      const formData = new FormData(form);
      const payload = {
        room_type_id: this.state.selectedRoom.id,
        check_in: this.state.checkIn,
        check_out: this.state.checkOut,
        adults: this.state.adults,
        children: this.state.children,
        room_count: this.state.roomCount,
        guest_name: (formData.get('guest_name') || '').trim(),
        guest_email: (formData.get('guest_email') || '').trim(),
        guest_phone: (formData.get('guest_phone') || '').trim(),
        special_requests: (formData.get('special_requests') || '').trim(),
      };

      if (!payload.guest_name || !payload.guest_email || !payload.guest_phone) {
        alert('Please complete all required fields (Name, Email, and Phone).');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerText = 'Locking Room Inventory...';

      try {
        const holdUrl = this.buildApiUrl('/booking/hold');
        const res = await fetch(holdUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': this.config.nonce || '',
          },
          body: JSON.stringify(payload),
        });

        const data = await res.json();

        if (!res.ok || !data.success) {
          throw new Error(data.message || 'Unable to reserve inventory.');
        }

        this.state.currentHold = Object.assign({}, data, {
          guest_name: payload.guest_name,
          guest_email: payload.guest_email,
          guest_phone: payload.guest_phone,
        });
        this.renderPaymentStep(data);

      } catch (err) {
        console.error('[Hotel Management] Hold failure:', err);
        const errDiv = document.createElement('div');
        errDiv.id = 'hm-checkout-error';
        errDiv.style.cssText = 'color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; font-size: 0.9rem; margin-top: 14px;';
        errDiv.innerText = 'Reservation Hold Failed: ' + err.message;
        form.appendChild(errDiv);

        submitBtn.disabled = false;
        submitBtn.innerText = 'Lock Room & Proceed to Payment';
      }
    },

    renderPaymentStep(holdData) {
      const body = document.getElementById('hm-modal-content-slot');
      if (!body) return;

      const pricing = holdData.pricing;
      const rzpData = holdData.razorpay || {};
      const isMock = !!rzpData.is_mock;

      body.innerHTML = `
        <div class="hm-hold-timer-banner">
          <span>⏳ Room inventory locked for you:</span>
          <span class="hm-hold-countdown" id="hm-hold-timer">15:00</span>
        </div>

        ${isMock ? `
          <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <span>🧪</span>
            <div><strong>Sandbox Test Mode Active:</strong> Instant simulation enabled. Clicking pay will finalize the booking.</div>
          </div>
        ` : ''}

        <div class="hm-quote-summary">
          <div class="hm-quote-row">
            <span>Booking Reference</span>
            <strong style="color:#0f172a;">${holdData.booking_number}</strong>
          </div>
          <div class="hm-quote-row">
            <span>Room Category</span>
            <span>${this.state.selectedRoom.title}</span>
          </div>
          <div class="hm-quote-row">
            <span>Nights & Rooms</span>
            <span>${pricing.total_nights} Nights &times; ${pricing.room_count} Room(s)</span>
          </div>
          <div class="hm-quote-row">
            <span>Subtotal</span>
            <span>${this.config.currencySymbol}${Number(pricing.subtotal).toLocaleString()}</span>
          </div>
          <div class="hm-quote-row">
            <span>GST / Taxes (${pricing.tax_percentage}%)</span>
            <span>${this.config.currencySymbol}${Number(pricing.tax_amount).toLocaleString()}</span>
          </div>
          <div class="hm-quote-row total">
            <span>Total Payable Amount</span>
            <span style="color:#b45309;">${this.config.currencySymbol}${Number(pricing.total_amount).toLocaleString()}</span>
          </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:10px; margin-top:20px;">
          <button type="button" id="hm-trigger-rzp-btn" class="hm-btn-search" style="justify-content:center; padding:15px; font-size:1.05rem;">
            ${isMock ? 'Complete Test Reservation (Instant Confirmation)' : `Pay ${this.config.currencySymbol}${Number(pricing.total_amount).toLocaleString()} via Razorpay Secure`}
          </button>

          ${!isMock ? `
            <button type="button" id="hm-trigger-mock-btn" class="hm-btn-book" style="background:#475569; justify-content:center; padding:10px; font-size:0.85rem;">
              ⚡ Instant Test Payment (Sandbox Bypass)
            </button>
          ` : ''}
        </div>
      `;

      this.startCountdownTimer(holdData.ttl_seconds || 900);

      const payBtn = document.getElementById('hm-trigger-rzp-btn');
      if (payBtn) {
        payBtn.addEventListener('click', () => {
          this.executeRazorpayCheckout(holdData);
        });
      }

      const mockBtn = document.getElementById('hm-trigger-mock-btn');
      if (mockBtn) {
        mockBtn.addEventListener('click', () => {
          const mockPaymentId = 'pay_mock_' + Math.random().toString(36).substring(2, 12);
          const mockSignature = 'sig_mock_' + Math.random().toString(36).substring(2, 12);
          this.verifyPayment({
            booking_id: holdData.booking_id,
            razorpay_order_id: holdData.razorpay.order_id,
            razorpay_payment_id: mockPaymentId,
            razorpay_signature: mockSignature,
          });
        });
      }
    },

    startCountdownTimer(durationSeconds) {
      let remaining = durationSeconds;
      const display = document.getElementById('hm-hold-timer');

      if (this.state.timerInterval) clearInterval(this.state.timerInterval);

      this.state.timerInterval = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
          clearInterval(this.state.timerInterval);
          if (display) display.innerText = '00:00 (Expired)';
          alert('Your 15-minute room hold has expired. Please restart search to secure current rates.');
          this.closeCheckoutModal();
          return;
        }

        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        if (display) {
          display.innerText = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
      }, 1000);
    },

    executeRazorpayCheckout(holdData) {
      const rzpData = holdData.razorpay || {};

      // Handle Sandbox / Mock Mode
      if (rzpData.is_mock) {
        const mockPaymentId = 'pay_mock_' + Math.random().toString(36).substring(2, 12);
        const mockSignature = 'sig_mock_' + Math.random().toString(36).substring(2, 12);

        this.verifyPayment({
          booking_id: holdData.booking_id,
          razorpay_order_id: rzpData.order_id,
          razorpay_payment_id: mockPaymentId,
          razorpay_signature: mockSignature,
        });
        return;
      }

      // Live / Real Razorpay Checkout integration
      if (typeof window.Razorpay === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.onload = () => this.launchRazorpayModal(holdData);
        script.onerror = () => {
          console.warn('[Hotel Management] Razorpay script could not be loaded from CDN.');
          if (confirm('Razorpay checkout script could not be loaded (offline/blocked). Would you like to complete simulated payment in test mode?')) {
            const mockPaymentId = 'pay_mock_' + Math.random().toString(36).substring(2, 12);
            const mockSignature = 'sig_mock_' + Math.random().toString(36).substring(2, 12);
            this.verifyPayment({
              booking_id: holdData.booking_id,
              razorpay_order_id: rzpData.order_id,
              razorpay_payment_id: mockPaymentId,
              razorpay_signature: mockSignature,
            });
          }
        };
        document.body.appendChild(script);
      } else {
        this.launchRazorpayModal(holdData);
      }
    },

    launchRazorpayModal(holdData) {
      const rzpData = holdData.razorpay;
      const options = {
        key: rzpData.key_id,
        amount: rzpData.amount,
        currency: rzpData.currency,
        name: 'The Grand Azure Resort',
        description: `Booking #${holdData.booking_number}`,
        order_id: rzpData.order_id,
        prefill: {
          name: this.state.currentHold?.guest_name || '',
          email: this.state.currentHold?.guest_email || '',
          contact: this.state.currentHold?.guest_phone || '',
        },
        handler: (response) => {
          this.verifyPayment({
            booking_id: holdData.booking_id,
            razorpay_order_id: response.razorpay_order_id,
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_signature: response.razorpay_signature,
          });
        },
        modal: {
          ondismiss: () => {
            console.log('[Hotel Management] Razorpay modal dismissed.');
          },
        },
        theme: { color: '#b45309' },
      };

      try {
        const rzp = new window.Razorpay(options);
        rzp.on('payment.failed', function (resp) {
          alert('Payment was declined by Razorpay: ' + (resp.error?.description || 'Unknown reason'));
        });
        rzp.open();
      } catch (err) {
        console.error('[Hotel Management] Error opening Razorpay modal:', err);
        alert('Could not initialize Razorpay checkout: ' + err.message);
      }
    },

    async verifyPayment(verificationPayload) {
      const body = document.getElementById('hm-modal-content-slot');
      if (body) {
        body.innerHTML = `
          <div style="text-align:center; padding: 40px 20px;">
            <div style="display:inline-block; width:40px; height:40px; border:3px solid #e2e8f0; border-top-color: #10b981; border-radius:50%; animation: spin 0.8s linear infinite;"></div>
            <h4 style="margin-top:16px; color:#0f172a;">Verifying Cryptographic Payment Signature...</h4>
            <p style="color:#64748b; font-size:0.9rem;">Finalizing your reservation securely.</p>
          </div>
        `;
      }

      try {
        const verifyUrl = this.buildApiUrl('/booking/verify');
        const res = await fetch(verifyUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': this.config.nonce || '',
          },
          body: JSON.stringify(verificationPayload),
        });

        const data = await res.json();

        if (!res.ok || !data.success) {
          throw new Error(data.message || 'Payment signature verification failed.');
        }

        if (this.state.timerInterval) clearInterval(this.state.timerInterval);

        this.renderConfirmationVoucher(data.booking);

      } catch (err) {
        alert('Payment Confirmation Failed: ' + err.message);
      }
    },

    renderConfirmationVoucher(booking) {
      const body = document.getElementById('hm-modal-content-slot');
      if (!body) return;

      body.innerHTML = `
        <div class="hm-voucher-pass">
          <div class="hm-voucher-header">
            <span class="hm-voucher-badge">Reservation Confirmed</span>
            <div class="hm-voucher-code">${booking.booking_number}</div>
            <p style="color:#64748b; font-size:0.85rem; margin-top:4px;">Present this digital pass at reception upon arrival</p>
          </div>

          <div style="border-top:1px dashed #cbd5e1; border-bottom:1px dashed #cbd5e1; padding: 18px 0; margin: 18px 0; font-size:0.9rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
              <span style="color:#64748b;">Primary Guest</span>
              <strong>${booking.guest_name}</strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
              <span style="color:#64748b;">Check-in Date</span>
              <strong>${booking.check_in_date} (from 14:00)</strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
              <span style="color:#64748b;">Check-out Date</span>
              <strong>${booking.check_out_date} (by 11:00)</strong>
            </div>
            <div style="display:flex; justify-content:space-between;">
              <span style="color:#64748b;">Total Paid</span>
              <strong style="color:#15803d;">${this.config.currencySymbol}${Number(booking.paid_amount).toLocaleString()}</strong>
            </div>
          </div>

          <div style="display:flex; gap:12px; margin-top: 24px;">
            <button type="button" onclick="window.print()" class="hm-btn-search" style="flex:1; justify-content:center;">
              Print / Save Pass
            </button>
            <button type="button" onclick="location.reload()" class="hm-btn-book" style="flex:1; justify-content:center;">
              Done
            </button>
          </div>
        </div>
      `;
    },
  };

  document.addEventListener('DOMContentLoaded', () => {
    HotelApp.init();
  });
})();

