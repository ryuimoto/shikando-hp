/**
 * POST /api/webhook
 * Stripe Webhook - checkout.session.completed を処理
 * → Google Calendar にイベント登録 + Google Sheets に記録
 */

import Stripe from 'stripe';
import { COURSES } from './_config.js';
import { createCalendarEvent, appendToSheet, toSheetRow } from './_google.js';

// Vercel: raw body を取得するために bodyParser を無効化
export const config = {
  api: {
    bodyParser: false,
  },
};

function getRawBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    req.on('data', chunk => chunks.push(chunk));
    req.on('end', () => resolve(Buffer.concat(chunks)));
    req.on('error', reject);
  });
}

export default async function handler(req, res) {
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Method Not Allowed' });
  }

  const stripe = new Stripe(process.env.STRIPE_SECRET_KEY);
  const sig = req.headers['stripe-signature'];
  const rawBody = await getRawBody(req);

  let event;
  try {
    event = stripe.webhooks.constructEvent(rawBody, sig, process.env.STRIPE_WEBHOOK_SECRET);
  } catch (err) {
    console.error('Webhook署名検証エラー:', err.message);
    return res.status(400).json({ error: 'Webhook signature verification failed' });
  }

  if (event.type === 'checkout.session.completed') {
    const session = event.data.object;
    const meta = session.metadata || {};
    const courseInfo = COURSES[meta.course];

    if (!courseInfo) {
      console.error('不明なコース:', meta.course);
      return res.status(200).json({ received: true });
    }

    // Google Calendar にイベント登録（電話セッションのみ）
    if (courseInfo.type === 'phone' && meta.date && meta.time) {
      try {
        await createCalendarEvent({
          summary: `【予約】${meta.name} - ${courseInfo.name}`,
          description: [
            `メール: ${meta.email || session.customer_email}`,
            `電話: ${meta.phone}`,
            `生年月日: ${meta.birthdate}`,
            `出生時刻: ${meta.birthtime}`,
            `相談内容: ${meta.message}`,
            `Stripe Session: ${session.id}`,
          ].join('\n'),
          date: meta.date,
          time: meta.time,
          durationMinutes: courseInfo.duration,
        });
      } catch (err) {
        console.error('カレンダー登録エラー:', err);
      }
    }

    // Google Sheets に記録
    try {
      const row = toSheetRow({
        course: meta.course,
        courseName: courseInfo.name,
        date: meta.date,
        time: meta.time,
        name: meta.name,
        email: meta.email || session.customer_email,
        phone: meta.phone,
        birthdate: meta.birthdate,
        birthtime: meta.birthtime,
        message: meta.message,
        paymentStatus: 'paid',
      });
      await appendToSheet(row);
    } catch (err) {
      console.error('スプレッドシート記録エラー:', err);
    }
  }

  return res.status(200).json({ received: true });
}
