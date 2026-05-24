// ── إعدادات نظام الإشعارات ──
// ── فترة الاستطلاع: كل 3 ثوانٍ ──
const POLL_INTERVAL = 3000;
const STORAGE_KEY = 'bets_shown_notifications';
// ── مفتاح تخزين الإشعارات المُسكتة من الجرس ──
const DISMISSED_KEY = 'bets_dismissed_notifications';
// مدة إخفاء الإشعار بعد الضغط عليه (ساعتان)
const DISMISS_DURATION = 2 * 60 * 60 * 1000;
// AudioContext يُهيَّأ عند أول تفاعل من المستخدم
let audioCtx = null;
document.addEventListener('click', () => {
  if (!audioCtx) {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
  }
});
const API_URL = '/api/notifications';

// ── قراءة معرّفات الإشعارات المعروضة مسبقًا من التخزين المحلي ──
function getShownIds() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    return stored ? JSON.parse(stored) : [];
  } catch {
    return [];
  }
}

// ── تسجيل إشعار كـ «تم عرضه» مع الحد الأقصى 100 معرّف ──
function markAsShown(id) {
  const shown = getShownIds();
  if (!shown.includes(id)) {
    shown.push(id);
  }
  const trimmed = shown.slice(-100);
  localStorage.setItem(STORAGE_KEY, JSON.stringify(trimmed));
}


// جلب كائنات الإشعارات المُسكتة كاملةً مع وقت الإسكات
function getDismissedObjects() {
  try {
    const stored = JSON.parse(localStorage.getItem(DISMISSED_KEY) || '[]');
    const now = Date.now();
    return stored.filter(
      item => item && typeof item === 'object'
        && item.time
        && (now - item.time) < DISMISS_DURATION
    );
  } catch { return []; }
}

// تسجيل الإشعار كمُسكَت مع حفظ وقت الإسكات
function dismissNotification(id) {
  try {
    const stored = JSON.parse(localStorage.getItem(DISMISSED_KEY) || '[]');
    const filtered = stored.filter(item => item.id !== id);
    filtered.push({ id, time: Date.now() });
    localStorage.setItem(DISMISSED_KEY, JSON.stringify(filtered.slice(-200)));
  } catch { }
}

// ── تشغيل صوت تنبيه عبر AudioContext المُهيَّأ مسبقًا ──
function playSound() {
  try {
    if (!audioCtx) return;
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.type = 'sine';
    osc.frequency.value = 880;
    gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.5);
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    osc.start();
    osc.stop(audioCtx.currentTime + 0.5);
  } catch { }
}

// ── عرض إشعار المتصفح الأصلي ──
function showBrowserNotification(notification) {
  if (Notification.permission !== 'granted') {
    return;
  }

  const browserNotif = new Notification(notification.title, {
    body: notification.message,
    icon: '/favicon.ico',
    tag: notification.id,
  });

  browserNotif.onclick = () => {
    window.focus();
    window.location = notification.url;
  };
}

// ── بناء HTML لعنصر تنبيه واحد في القائمة المنسدلة ──
function buildNotificationItem(notification) {
  const severityColors = {
    danger: 'text-danger',
    warning: 'text-warning',
    info: 'text-info',
  };
  const color = severityColors[notification.severity] || 'text-info';

  const item = document.createElement('a');
  item.href = notification.url;
  item.className = 'dropdown-item notification-item py-2 border-bottom';
  // ── إخفاء الإشعار من الجرس عند النقر عليه ──
  item.addEventListener('click', (e) => {
    dismissNotification(notification.id);
  });

  const row = document.createElement('div');
  row.className = 'd-flex align-items-start gap-2';

  const icon = document.createElement('i');
  icon.className = 'bi bi-circle-fill ' + color + ' mt-1 small';

  const content = document.createElement('div');

  const title = document.createElement('div');
  title.className = 'fw-semibold small';
  title.textContent = notification.title;

  const message = document.createElement('div');
  message.className = 'text-muted';
  message.style.fontSize = '0.8rem';
  message.textContent = notification.message;

  content.appendChild(title);
  content.appendChild(message);
  row.appendChild(icon);
  row.appendChild(content);
  item.appendChild(row);

  return item;
}

// ── تحديث شارة الجرس وقائمة الإشعارات المنسدلة ──
function updateBell(notifications) {
  const badge = document.getElementById('notification-bell-badge');
  const list = document.getElementById('notification-dropdown-list');
  const bellBtn = document.getElementById('notificationBell');

  if (!badge || !list) {
    return;
  }

  const count = notifications.length;

  if (count > 0) {
    badge.style.display = '';
    badge.textContent = count;
    if (bellBtn) {
      bellBtn.classList.add('bell-active');
    }
  } else {
    badge.style.display = 'none';
    if (bellBtn) {
      bellBtn.classList.remove('bell-active');
    }
  }

  list.innerHTML = '';

  if (count === 0) {
    const empty = document.createElement('div');
    empty.className = 'dropdown-item text-muted text-center py-3';
    empty.textContent = 'لا توجد تنبيهات';
    list.appendChild(empty);
    return;
  }

  notifications.forEach((notification) => {
    list.appendChild(buildNotificationItem(notification));
  });
}

// جلب التنبيهات من الخادم ومعالجتها
async function fetchNotifications() {
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    const response = await fetch(API_URL, {
      headers: {
        'X-CSRF-TOKEN': csrfToken ? csrfToken.content : '',
        Accept: 'application/json',
      },
    });

    const data = await response.json();
    const notifications = data.notifications || [];
    const activeIds = notifications.map(n => n.id);

    // تحديث المُسكَتة: الاحتفاظ بالكائنات ضمن المدة وللإشعارات النشطة فقط
    const now = Date.now();
    const rawDismissed = JSON.parse(localStorage.getItem(DISMISSED_KEY) || '[]');
    const validDismissed = rawDismissed.filter(
      item => item && item.id && item.time
        && (now - item.time) < DISMISS_DURATION
        && activeIds.includes(item.id)
    );
    localStorage.setItem(DISMISSED_KEY, JSON.stringify(validDismissed));
    const dismissedIds = validDismissed.map(item => item.id);

    // الإشعارات المرئية: استبعاد المُسكَتة أولاً
    const visibleNotifications = notifications.filter(
      n => !dismissedIds.includes(n.id)
    );

    // إشعارات جديدة: من المرئية فقط وغير المعروضة مسبقاً
    const shownIds = getShownIds();
    const newNotifications = visibleNotifications.filter(
      n => !shownIds.includes(n.id)
    );

    if (newNotifications.length > 0) {
      playSound();
      newNotifications.forEach(notification => {
        showBrowserNotification(notification);
        markAsShown(notification.id);
      });
    }

    updateBell(visibleNotifications);
  } catch { }
}

// ── تهيئة النظام عند تحميل الصفحة ──
document.addEventListener('DOMContentLoaded', () => {
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
  }

  fetchNotifications();
  setInterval(fetchNotifications, POLL_INTERVAL);
});
