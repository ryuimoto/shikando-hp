/**
 * POST /api/create-checkout
 * Stripe Checkout Session を作成し、決済URLを返す
 */

import Stripe from 'stripe';
import { COURSES, BUSINESS_HOURS, validateOrigin, validateBookingInput, sendJson, sendError } from './_config.js';
import { isSlotAvailable } from './_google.js';

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

  // 無料コースはこのエンドポイントでは処理しない
  if (courseInfo.free) {
    return sendError(res, 400, '無料コースは /api/create-free-booking をご利用ください');
  }

  // 電話セッションの場合、空き枠を再確認
  if (courseInfo.type === 'phone') {
    try {
      const available = await isSlotAvailable(body.date, body.time, courseInfo.duration, BUSINESS_HOURS);
      if (!available) {
        return sendError(res, 409, 'ご指定の日時は既に予約済みです。別の日時をお選びください。');
      }
    } catch (err) {
      console.error('空き枠確認エラー:', err);
      return sendError(res, 500, '空き枠の確認に失敗しました');
    }
  }

  // Stripe Checkout Session 作成
  try {
    const stripe = new Stripe(process.env.STRIPE_SECRET_KEY);
    const siteUrl = process.env.SITE_URL || 'https://shikando.com';

    const description = courseInfo.type === 'phone'
      ? `${courseInfo.name} - ${body.date} ${body.time}〜`
      : courseInfo.name;

    const session = await stripe.checkout.sessions.create({
      mode: 'payment',
      payment_method_types: ['card'],
      locale: 'ja',
      line_items: [{
        price_data: {
          currency: 'jpy',
          product_data: {
            name: courseInfo.name,
            description,
          },
          unit_amount: courseInfo.price,
        },
        quantity: 1,
      }],
      customer_email: body.email,
      metadata: {
        course: body.course,
        date: body.date || '',
        time: body.time || '',
        name: body.name,
        phone: body.phone || '',
        birthdate: body.birthdate || '',
        birthtime: body.birthtime || '',
        message: (body.message || '').substring(0, 500),
      },
      success_url: `${siteUrl}/reservation/success/?session_id={CHECKOUT_SESSION_ID}`,
      cancel_url: `${siteUrl}/reservation/cancel/`,
    });

    return sendJson(res, 200, { url: session.url });
  } catch (err) {
    console.error('Stripe Checkout作成エラー:', err);
    return sendError(res, 500, '決済セッションの作成に失敗しました');
  }
}
