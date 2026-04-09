/**
 * GET /api/available-slots
 * クエリ: ?date=2026-04-15&course=phone-standard
 * レスポンス: { slots: ["10:00", "10:30", ...] }
 */

import { COURSES, BUSINESS_HOURS, BOOKING_RANGE, sendJson, sendError } from './_config.js';
import { getEventsForDate, calculateAvailableSlots } from './_google.js';

export default async function handler(req, res) {
  if (req.method !== 'GET') {
    return sendError(res, 405, 'Method Not Allowed');
  }

  const { date, course } = req.query;

  // コースバリデーション
  const courseInfo = COURSES[course];
  if (!courseInfo || courseInfo.type !== 'phone') {
    return sendError(res, 400, '無効なコースが指定されています');
  }

  // 日付バリデーション
  if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
    return sendError(res, 400, '日付の形式が正しくありません');
  }

  const targetDate = new Date(`${date}T00:00:00+09:00`);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const diffDays = Math.ceil((targetDate - today) / (1000 * 60 * 60 * 24));
  if (diffDays < BOOKING_RANGE.minDays || diffDays > BOOKING_RANGE.maxDays) {
    return sendError(res, 400, `予約可能期間は${BOOKING_RANGE.minDays}日後〜${BOOKING_RANGE.maxDays}日後です`);
  }

  try {
    const events = await getEventsForDate(date);
    const slots = calculateAvailableSlots(events, courseInfo.duration, BUSINESS_HOURS);
    return sendJson(res, 200, { slots });
  } catch (err) {
    console.error('空き枠取得エラー:', err);
    return sendError(res, 500, '空き枠の取得に失敗しました');
  }
}
