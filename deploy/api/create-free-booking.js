/**
 * POST /api/create-free-booking
 * 無料コース（お試し鑑定・ワンポイント初回無料）の予約処理
 * → Google Calendar にイベント登録 + Google Sheets に記録
 */

import { COURSES, BUSINESS_HOURS, validateOrigin, validateBookingInput, sendJson, sendError } from './_config.js';
import { isSlotAvailable, createCalendarEvent, appendToSheet, toSheetRow } from './_google.js';

export default async function handler(req, res) {
  if (req.method !== 'POST') {
    return sendError(res, 405, 'Method Not Allowed');
  }

  if (!validateOrigin(req)) {
    return sendError(res, 403, 'Forbidden');
  }

  const body = req.body;
  const errors = validateBookingInput(body);
  if (errors.length > 0) {
    return sendError(res, 400, errors[0]);
  }

  const courseInfo = COURSES[body.course];

  // 無料コースのみ受付
  if (!courseInfo.free) {
    return sendError(res, 400, '有料コースは /api/create-checkout をご利用ください');
  }

  // 電話セッションの場合、空き枠を確認 + カレンダー登録
  if (courseInfo.type === 'phone') {
    try {
      const available = await isSlotAvailable(body.date, body.time, courseInfo.duration, BUSINESS_HOURS);
      if (!available) {
        return sendError(res, 409, 'ご指定の日時は既に予約済みです。別の日時をお選びください。');
      }

      await createCalendarEvent({
        summary: `【無料予約】${body.name} - ${courseInfo.name}`,
        description: [
          `メール: ${body.email}`,
          `電話: ${body.phone || '未入力'}`,
          `生年月日: ${body.birthdate || '未入力'}`,
          `出生時刻: ${body.birthtime || '未入力'}`,
          `相談内容: ${body.message || '未入力'}`,
        ].join('\n'),
        date: body.date,
        time: body.time,
        durationMinutes: courseInfo.duration,
      });
    } catch (err) {
      console.error('カレンダー登録エラー:', err);
      return sendError(res, 500, '予約の登録に失敗しました');
    }
  }

  // Google Sheets に記録
  try {
    const row = toSheetRow({
      course: body.course,
      courseName: courseInfo.name,
      date: body.date,
      time: body.time,
      name: body.name,
      email: body.email,
      phone: body.phone,
      birthdate: body.birthdate,
      birthtime: body.birthtime,
      message: body.message,
      paymentStatus: 'free',
    });
    await appendToSheet(row);
  } catch (err) {
    console.error('スプレッドシート記録エラー:', err);
    // カレンダー登録は成功しているので、シート記録失敗は警告のみ
  }

  return sendJson(res, 200, { success: true });
}
