/**
 * 士観道 予約システム - 共通設定
 */

export const COURSES = {
  'phone-trial':        { name: 'お試し鑑定（30分）',           duration: 30, price: 0,    type: 'phone', free: true },
  'phone-standard':     { name: 'スタンダード鑑定（30分）',     duration: 30, price: 3000, type: 'phone' },
  'phone-deep-60':      { name: 'じっくり鑑定（60分）',         duration: 60, price: 6000, type: 'phone' },
  'phone-deep-90':      { name: 'じっくり鑑定（90分）',         duration: 90, price: 9000, type: 'phone' },
  'chat-onepoint':      { name: 'ワンポイント鑑定',             duration: 0,  price: 1000, type: 'chat' },
  'chat-onepoint-free': { name: 'ワンポイント鑑定（初回無料）', duration: 0,  price: 0,    type: 'chat', free: true },
  'chat-threepoint':    { name: 'スリーポイント鑑定',           duration: 0,  price: 3000, type: 'chat' },
};

// 営業時間
export const BUSINESS_HOURS = { start: 10, end: 22 };

// 予約可能範囲（今日から何日先まで）
export const BOOKING_RANGE = { minDays: 1, maxDays: 30 };

// CORS / Origin 検証
export function validateOrigin(req) {
  const origin = req.headers['origin'] || req.headers['referer'] || '';
  const siteUrl = process.env.SITE_URL || '';
  if (siteUrl && !origin.startsWith(siteUrl)) {
    return false;
  }
  return true;
}

// 入力バリデーション
export function validateBookingInput(body) {
  const { course, name, email } = body;
  const errors = [];

  if (!course || !COURSES[course]) {
    errors.push('無効なコースが指定されています');
  }

  if (!name || name.trim().length === 0) {
    errors.push('お名前を入力してください');
  }

  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.push('有効なメールアドレスを入力してください');
  }

  const courseInfo = COURSES[course];
  if (courseInfo && courseInfo.type === 'phone') {
    const { date, time } = body;
    if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
      errors.push('日付を選択してください');
    }
    if (!time || !/^\d{2}:\d{2}$/.test(time)) {
      errors.push('時間を選択してください');
    }
  }

  return errors;
}

// JSON レスポンスヘルパー
export function sendJson(res, status, data) {
  res.status(status).json(data);
}

export function sendError(res, status, message) {
  res.status(status).json({ error: message });
}
