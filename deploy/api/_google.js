/**
 * 士観道 予約システム - Google API 共通モジュール
 * Google Calendar / Google Sheets 操作
 */

import { google } from 'googleapis';

let authClient = null;

function getAuth() {
  if (authClient) return authClient;
  const credentials = JSON.parse(process.env.GOOGLE_SERVICE_ACCOUNT_KEY);
  authClient = new google.auth.GoogleAuth({
    credentials,
    scopes: [
      'https://www.googleapis.com/auth/calendar',
      'https://www.googleapis.com/auth/spreadsheets',
    ],
  });
  return authClient;
}

// ========== Google Calendar ==========

/**
 * 指定日の既存イベントを取得
 */
export async function getEventsForDate(dateStr) {
  const auth = getAuth();
  const calendar = google.calendar({ version: 'v3', auth });
  const calendarId = process.env.GOOGLE_CALENDAR_ID;

  const dayStart = new Date(`${dateStr}T00:00:00+09:00`);
  const dayEnd = new Date(`${dateStr}T23:59:59+09:00`);

  const res = await calendar.events.list({
    calendarId,
    timeMin: dayStart.toISOString(),
    timeMax: dayEnd.toISOString(),
    singleEvents: true,
    orderBy: 'startTime',
    timeZone: 'Asia/Tokyo',
  });

  return res.data.items || [];
}

/**
 * 営業時間内の空きスロットを計算
 */
export function calculateAvailableSlots(events, durationMinutes, businessHours) {
  const { start: bizStart, end: bizEnd } = businessHours;
  const slots = [];

  // 既存イベントの時間帯を取得（分単位）
  const busyRanges = events
    .filter(e => e.start && e.start.dateTime)
    .map(e => {
      const s = new Date(e.start.dateTime);
      const eEnd = new Date(e.end.dateTime);
      return {
        start: s.getHours() * 60 + s.getMinutes(),
        end: eEnd.getHours() * 60 + eEnd.getMinutes(),
      };
    });

  // 30分刻みでスロットを生成
  for (let m = bizStart * 60; m + durationMinutes <= bizEnd * 60; m += 30) {
    const slotEnd = m + durationMinutes;
    const isBusy = busyRanges.some(
      range => m < range.end && slotEnd > range.start
    );
    if (!isBusy) {
      const hours = String(Math.floor(m / 60)).padStart(2, '0');
      const mins = String(m % 60).padStart(2, '0');
      slots.push(`${hours}:${mins}`);
    }
  }

  return slots;
}

/**
 * Googleカレンダーにイベントを登録
 */
export async function createCalendarEvent({ summary, description, date, time, durationMinutes }) {
  const auth = getAuth();
  const calendar = google.calendar({ version: 'v3', auth });
  const calendarId = process.env.GOOGLE_CALENDAR_ID;

  const startDateTime = `${date}T${time}:00+09:00`;
  const endDate = new Date(new Date(startDateTime).getTime() + durationMinutes * 60 * 1000);
  const endHours = String(endDate.getHours()).padStart(2, '0');
  const endMins = String(endDate.getMinutes()).padStart(2, '0');
  const endDateTime = `${date}T${endHours}:${endMins}:00+09:00`;

  const res = await calendar.events.insert({
    calendarId,
    requestBody: {
      summary,
      description,
      start: { dateTime: startDateTime, timeZone: 'Asia/Tokyo' },
      end: { dateTime: endDateTime, timeZone: 'Asia/Tokyo' },
    },
  });

  return res.data;
}

/**
 * 指定スロットが空いているか確認
 */
export async function isSlotAvailable(date, time, durationMinutes, businessHours) {
  const events = await getEventsForDate(date);
  const slots = calculateAvailableSlots(events, durationMinutes, businessHours);
  return slots.includes(time);
}

// ========== Google Sheets ==========

/**
 * スプレッドシートに予約記録を追加
 */
export async function appendToSheet(rowData) {
  const auth = getAuth();
  const sheets = google.sheets({ version: 'v4', auth });
  const spreadsheetId = process.env.GOOGLE_SHEET_ID;

  await sheets.spreadsheets.values.append({
    spreadsheetId,
    range: '予約一覧!A:L',
    valueInputOption: 'USER_ENTERED',
    requestBody: {
      values: [rowData],
    },
  });
}

/**
 * 予約情報をシート用の行データに変換
 */
export function toSheetRow({ course, courseName, date, time, name, email, phone, birthdate, birthtime, message, paymentStatus }) {
  return [
    new Date().toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo' }),
    courseName,
    date || '-',
    time || '-',
    name,
    email,
    phone || '-',
    birthdate || '-',
    birthtime || '-',
    message || '-',
    paymentStatus,
    course,
  ];
}
