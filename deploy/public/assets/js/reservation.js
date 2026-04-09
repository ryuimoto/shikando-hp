/**
 * 士観道 予約システム - フロントエンドJS
 */

(function () {
  'use strict';

  // コース定義（サーバーと同期）
  const COURSES = {
    'phone-trial':        { name: 'お試し鑑定（30分）',           duration: 30, price: 0,    type: 'phone', free: true },
    'phone-standard':     { name: 'スタンダード鑑定（30分）',     duration: 30, price: 3000, type: 'phone' },
    'phone-deep-60':      { name: 'じっくり鑑定（60分）',         duration: 60, price: 6000, type: 'phone' },
    'phone-deep-90':      { name: 'じっくり鑑定（90分）',         duration: 90, price: 9000, type: 'phone' },
    'chat-onepoint':      { name: 'ワンポイント鑑定',             duration: 0,  price: 1000, type: 'chat' },
    'chat-onepoint-free': { name: 'ワンポイント鑑定（初回無料）', duration: 0,  price: 0,    type: 'chat', free: true },
    'chat-threepoint':    { name: 'スリーポイント鑑定',           duration: 0,  price: 3000, type: 'chat' },
  };

  // 状態管理
  const state = {
    currentStep: 1,
    course: null,
    date: null,
    time: null,
    calendarMonth: null, // Date object for displayed month
    availableSlots: [],
  };

  // DOM 要素
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => document.querySelectorAll(sel);

  // ===== 初期化 =====
  document.addEventListener('DOMContentLoaded', init);

  function init() {
    const today = new Date();
    state.calendarMonth = new Date(today.getFullYear(), today.getMonth(), 1);

    setupCourseCards();
    renderCalendar();
    setupCalendarNav();
    setupFormValidation();
    setupNavButtons();
    updateStepIndicator();
  }

  // ===== ステップ管理 =====
  function goToStep(step) {
    state.currentStep = step;
    $$('.step-content').forEach((el, i) => {
      el.classList.toggle('active', i + 1 === step);
    });
    updateStepIndicator();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function updateStepIndicator() {
    $$('.reservation-steps .step').forEach((el, i) => {
      const stepNum = i + 1;
      el.classList.remove('active', 'done');
      if (stepNum === state.currentStep) el.classList.add('active');
      else if (stepNum < state.currentStep) el.classList.add('done');
    });
  }

  function getTotalSteps() {
    const courseInfo = state.course ? COURSES[state.course] : null;
    // チャットセッションは日時選択をスキップ（ステップ2,3をスキップ）
    if (courseInfo && courseInfo.type === 'chat') return 3; // コース → 情報 → 確認
    return 5; // コース → 日付 → 時間 → 情報 → 確認
  }

  function getNextStep(current) {
    const courseInfo = state.course ? COURSES[state.course] : null;
    if (courseInfo && courseInfo.type === 'chat') {
      if (current === 1) return 4; // コース → 情報入力（スキップ2,3）
      if (current === 4) return 5; // 情報 → 確認
    }
    return current + 1;
  }

  function getPrevStep(current) {
    const courseInfo = state.course ? COURSES[state.course] : null;
    if (courseInfo && courseInfo.type === 'chat') {
      if (current === 4) return 1; // 情報入力 → コース
      if (current === 5) return 4; // 確認 → 情報入力
    }
    return current - 1;
  }

  // ===== コース選択 =====
  function setupCourseCards() {
    $$('.course-card').forEach(card => {
      card.addEventListener('click', () => {
        $$('.course-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        state.course = card.dataset.course;
        state.date = null;
        state.time = null;

        // 次へボタン有効化
        const nextBtn = $('#step1-next');
        if (nextBtn) nextBtn.disabled = false;
      });
    });
  }

  // ===== カレンダー =====
  function renderCalendar() {
    const grid = $('.calendar-grid');
    if (!grid) return;

    const year = state.calendarMonth.getFullYear();
    const month = state.calendarMonth.getMonth();

    // 月タイトル
    const monthTitle = $('.month-title');
    if (monthTitle) {
      monthTitle.textContent = `${year}年${month + 1}月`;
    }

    // 曜日ラベル
    const dayLabels = ['日', '月', '火', '水', '木', '金', '土'];
    let html = dayLabels.map(d => `<div class="day-label">${d}</div>`).join('');

    // 月初の曜日
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // 空セル
    for (let i = 0; i < firstDay; i++) {
      html += '<div class="day-cell"></div>';
    }

    // 日付セル
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    for (let d = 1; d <= daysInMonth; d++) {
      const cellDate = new Date(year, month, d);
      const diffDays = Math.ceil((cellDate - today) / (1000 * 60 * 60 * 24));
      const isAvailable = diffDays >= 1 && diffDays <= 30;
      const dayOfWeek = cellDate.getDay();

      const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      const isSelected = state.date === dateStr;

      let classes = 'day-cell';
      if (isAvailable) classes += ' available';
      if (isSelected) classes += ' selected';
      if (dayOfWeek === 0) classes += ' sunday';
      if (dayOfWeek === 6) classes += ' saturday';

      html += `<div class="${classes}" data-date="${dateStr}">${d}</div>`;
    }

    grid.innerHTML = html;

    // 日付クリックイベント
    grid.querySelectorAll('.day-cell.available').forEach(cell => {
      cell.addEventListener('click', () => {
        grid.querySelectorAll('.day-cell').forEach(c => c.classList.remove('selected'));
        cell.classList.add('selected');
        state.date = cell.dataset.date;

        const nextBtn = $('#step2-next');
        if (nextBtn) nextBtn.disabled = false;

        // 時間枠を取得
        fetchAvailableSlots();
      });
    });

    // ナビボタン状態更新
    updateCalendarNavButtons();
  }

  function setupCalendarNav() {
    const prevBtn = $('.calendar-prev');
    const nextBtn = $('.calendar-next');

    if (prevBtn) {
      prevBtn.addEventListener('click', () => {
        state.calendarMonth.setMonth(state.calendarMonth.getMonth() - 1);
        renderCalendar();
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        state.calendarMonth.setMonth(state.calendarMonth.getMonth() + 1);
        renderCalendar();
      });
    }
  }

  function updateCalendarNavButtons() {
    const today = new Date();
    const currentMonth = new Date(today.getFullYear(), today.getMonth(), 1);

    const prevBtn = $('.calendar-prev');
    const nextBtn = $('.calendar-next');

    if (prevBtn) {
      prevBtn.disabled = state.calendarMonth <= currentMonth;
    }

    if (nextBtn) {
      const maxMonth = new Date(today.getFullYear(), today.getMonth() + 1, 1);
      nextBtn.disabled = state.calendarMonth >= maxMonth;
    }
  }

  // ===== 時間枠 =====
  async function fetchAvailableSlots() {
    const slotsContainer = $('.time-slots-container');
    if (!slotsContainer) return;

    slotsContainer.innerHTML = '<div class="time-slots-loading">空き時間を確認中...</div>';
    state.time = null;

    try {
      const res = await fetch(`/api/available-slots?date=${state.date}&course=${state.course}`);
      const data = await res.json();

      if (!res.ok) {
        slotsContainer.innerHTML = `<div class="time-slots-empty">${data.error || 'エラーが発生しました'}</div>`;
        return;
      }

      if (data.slots.length === 0) {
        slotsContainer.innerHTML = '<div class="time-slots-empty">この日は空きがありません。別の日をお選びください。</div>';
        return;
      }

      state.availableSlots = data.slots;
      renderTimeSlots(data.slots);
    } catch (err) {
      slotsContainer.innerHTML = '<div class="time-slots-empty">空き時間の取得に失敗しました</div>';
    }
  }

  function renderTimeSlots(slots) {
    const container = $('.time-slots-container');
    let html = '<div class="time-slots">';
    slots.forEach(slot => {
      html += `<div class="time-slot" data-time="${slot}">${slot}</div>`;
    });
    html += '</div>';
    container.innerHTML = html;

    container.querySelectorAll('.time-slot').forEach(el => {
      el.addEventListener('click', () => {
        container.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
        el.classList.add('selected');
        state.time = el.dataset.time;

        const nextBtn = $('#step3-next');
        if (nextBtn) nextBtn.disabled = false;
      });
    });
  }

  // ===== フォームバリデーション =====
  function setupFormValidation() {
    const form = $('#reservation-form');
    if (!form) return;

    form.querySelectorAll('input, textarea').forEach(input => {
      input.addEventListener('input', () => {
        clearError(input);
        updateStep4NextButton();
      });
    });
  }

  function validateForm() {
    const form = $('#reservation-form');
    if (!form) return false;

    let valid = true;
    clearAllErrors();

    const name = form.querySelector('[name="name"]');
    const email = form.querySelector('[name="email"]');
    const phone = form.querySelector('[name="phone"]');
    const birthdate = form.querySelector('[name="birthdate"]');
    const birthtime = form.querySelector('[name="birthtime"]');

    if (!name.value.trim()) {
      showError(name, 'お名前を入力してください');
      valid = false;
    }

    if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
      showError(email, '有効なメールアドレスを入力してください');
      valid = false;
    }

    if (!phone.value.trim()) {
      showError(phone, '電話番号を入力してください');
      valid = false;
    }

    if (!birthdate.value) {
      showError(birthdate, '生年月日を入力してください');
      valid = false;
    }

    if (!birthtime.value) {
      showError(birthtime, '出生時刻を入力してください');
      valid = false;
    }

    return valid;
  }

  function showError(input, message) {
    const existing = input.parentElement.querySelector('.form-error');
    if (existing) existing.remove();
    const err = document.createElement('div');
    err.className = 'form-error';
    err.textContent = message;
    input.parentElement.appendChild(err);
    input.style.borderColor = '#c44';
  }

  function clearError(input) {
    const err = input.parentElement.querySelector('.form-error');
    if (err) err.remove();
    input.style.borderColor = '';
  }

  function clearAllErrors() {
    $$('.form-error').forEach(e => e.remove());
    $$('.reservation-form input, .reservation-form textarea').forEach(i => {
      i.style.borderColor = '';
    });
  }

  function updateStep4NextButton() {
    const form = $('#reservation-form');
    if (!form) return;
    const name = form.querySelector('[name="name"]').value.trim();
    const email = form.querySelector('[name="email"]').value.trim();
    const nextBtn = $('#step4-next');
    if (nextBtn) nextBtn.disabled = !(name && email);
  }

  // ===== 確認画面 =====
  function renderConfirmation() {
    const courseInfo = COURSES[state.course];
    const form = $('#reservation-form');

    const name = form.querySelector('[name="name"]').value.trim();
    const email = form.querySelector('[name="email"]').value.trim();
    const phone = form.querySelector('[name="phone"]').value.trim();
    const birthdate = form.querySelector('[name="birthdate"]').value;
    const birthtime = form.querySelector('[name="birthtime"]').value;
    const message = form.querySelector('[name="message"]').value.trim();

    const confirmBody = $('.confirm-body');
    if (!confirmBody) return;

    let rows = `
      <tr><th>コース</th><td>${courseInfo.name}</td></tr>
    `;

    if (courseInfo.type === 'phone') {
      const dateFormatted = state.date.replace(/(\d{4})-(\d{2})-(\d{2})/, '$1年$2月$3日');
      rows += `
        <tr><th>日時</th><td>${dateFormatted} ${state.time}〜</td></tr>
      `;
    }

    rows += `
      <tr><th>お名前</th><td>${escapeHtml(name)}</td></tr>
      <tr><th>メールアドレス</th><td>${escapeHtml(email)}</td></tr>
      <tr><th>電話番号</th><td>${escapeHtml(phone)}</td></tr>
      <tr><th>生年月日</th><td>${birthdate}</td></tr>
      <tr><th>出生時刻</th><td>${birthtime}</td></tr>
    `;

    if (message) {
      rows += `<tr><th>ご相談内容</th><td>${escapeHtml(message)}</td></tr>`;
    }

    confirmBody.innerHTML = `
      <table class="confirm-table">${rows}</table>
      <div class="confirm-price">
        <div class="price-label">お支払い金額</div>
        <div class="price-value ${courseInfo.free ? 'free' : ''}">
          ${courseInfo.free ? '無料' : `¥${courseInfo.price.toLocaleString()}`}
        </div>
      </div>
    `;

    // 送信ボタンのテキスト
    const submitBtn = $('#btn-submit');
    if (submitBtn) {
      if (courseInfo.free) {
        submitBtn.textContent = '予約する（無料）';
        submitBtn.classList.add('free');
      } else {
        submitBtn.textContent = '予約・決済に進む';
        submitBtn.classList.remove('free');
      }
    }
  }

  // ===== ナビゲーション =====
  function setupNavButtons() {
    // 各ステップの「次へ」ボタン
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;

      const action = btn.dataset.action;

      if (action === 'next') {
        const nextStep = getNextStep(state.currentStep);

        // ステップ4→5の時に確認画面をレンダリング
        if (nextStep === 5) {
          if (!validateForm()) return;
          renderConfirmation();
        }

        goToStep(nextStep);
      }

      if (action === 'back') {
        goToStep(getPrevStep(state.currentStep));
      }

      if (action === 'submit') {
        handleSubmit(btn);
      }
    });
  }

  // ===== 送信処理 =====
  async function handleSubmit(btn) {
    const courseInfo = COURSES[state.course];
    const form = $('#reservation-form');

    const body = {
      course: state.course,
      date: state.date || '',
      time: state.time || '',
      name: form.querySelector('[name="name"]').value.trim(),
      email: form.querySelector('[name="email"]').value.trim(),
      phone: form.querySelector('[name="phone"]').value.trim(),
      birthdate: form.querySelector('[name="birthdate"]').value,
      birthtime: form.querySelector('[name="birthtime"]').value,
      message: form.querySelector('[name="message"]').value.trim(),
    };

    btn.disabled = true;
    btn.classList.add('btn-loading');
    hideError();

    try {
      const endpoint = courseInfo.free ? '/api/create-free-booking' : '/api/create-checkout';
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });

      const data = await res.json();

      if (!res.ok) {
        showGlobalError(data.error || '予約に失敗しました');
        btn.disabled = false;
        btn.classList.remove('btn-loading');
        return;
      }

      if (courseInfo.free) {
        // 無料予約完了 → 成功ページへ
        window.location.href = '/reservation/success/';
      } else {
        // Stripe Checkout へリダイレクト
        window.location.href = data.url;
      }
    } catch (err) {
      showGlobalError('通信エラーが発生しました。もう一度お試しください。');
      btn.disabled = false;
      btn.classList.remove('btn-loading');
    }
  }

  function showGlobalError(message) {
    let errEl = $('.reservation-error');
    if (!errEl) {
      errEl = document.createElement('div');
      errEl.className = 'reservation-error';
      const container = $('.confirm-body');
      if (container) container.parentElement.insertBefore(errEl, container);
    }
    errEl.textContent = message;
    errEl.style.display = 'block';
  }

  function hideError() {
    const errEl = $('.reservation-error');
    if (errEl) errEl.style.display = 'none';
  }

  // ===== ユーティリティ =====
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
})();
