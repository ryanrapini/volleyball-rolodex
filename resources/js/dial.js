/*
 * Phone numbers are stored however they were typed — "(412) 952-8506" — but the
 * tel: and sms: schemes want digits. On Android and iOS these hand off to the
 * dialler and the messaging app; on a desktop browser they usually do nothing,
 * which is why the buttons are only shown when there is a number to use.
 */

const digits = (phone) => String(phone ?? '').replace(/[^\d+]/g, '');

export const telHref = (phone) => `tel:${digits(phone)}`;

export const smsHref = (phone) => `sms:${digits(phone)}`;

export const canDial = (phone) => digits(phone).replace(/\D/g, '').length >= 7;
